#!/usr/bin/env python3
"""Apply content edits or a page plan to a PDF with PyMuPDF.

Content edits keep the original page stream where they can: selected glyphs are
removed, highlights are real annotations, and form values are written into the
AcroForm. Page structure is a separate pass.
"""

from __future__ import annotations

import json
import sys
import traceback
from pathlib import Path

import fitz

FONT_CANDIDATES = (
    "/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf",
    "/usr/share/fonts/truetype/liberation2/LiberationSans-Regular.ttf",
    "/usr/share/fonts/liberation/LiberationSans-Regular.ttf",
    "/Library/Fonts/Arial.ttf",
    "/System/Library/Fonts/Supplemental/Arial.ttf",
    "/System/Library/Fonts/Supplemental/Arial Unicode.ttf",
)


class EditError(Exception):
    """A problem the user can act on. The source PDF is left unchanged."""


def main(argv: list[str]) -> int:
    if len(argv) != 5 or argv[1] not in {"apply", "pages"}:
        print(
            json.dumps({"error": "Could not edit this PDF."}),
            file=sys.stdout,
        )
        return 2

    command, source, destination, spec_path = argv[1:]

    try:
        spec = json.loads(Path(spec_path).read_text(encoding="utf-8"))
        if command == "apply":
            pages = apply_content(source, destination, spec)
        else:
            pages = apply_pages(source, destination, spec)
    except EditError as error:
        print(json.dumps({"error": str(error)}))
        return 3
    except Exception:
        traceback.print_exc()
        print(json.dumps({"error": "Could not edit this PDF."}))
        return 1

    print(json.dumps({"pages": pages}))
    return 0


def apply_content(source: str, destination: str, spec: dict) -> int:
    document = fitz.open(source)
    try:
        fontfile = find_font()
        _preflight_replacements(spec.get("replacements") or [], fontfile)
        _fields(document, spec.get("fields") or [])
        if spec.get("flatten"):
            document.bake(annots=False, widgets=True)
        if _redact(document, spec):
            # Redaction rebuilds the page and unbinds existing widget objects.
            reopened = fitz.open(stream=document.tobytes(), filetype="pdf")
            document.close()
            document = reopened
        _covers(document, spec.get("covers") or [])
        _write_replacements(document, spec.get("replacements") or [], fontfile)
        _write_texts(document, spec.get("texts") or [], fontfile)
        _images(document, spec.get("images") or [])
        _highlights(document, spec.get("highlights") or [])
        document.save(destination, garbage=4, deflate=True)
        return document.page_count
    finally:
        document.close()


def apply_pages(source: str, destination: str, spec: dict) -> int:
    document = fitz.open(source)
    try:
        plan = spec.get("pages") or []
        if not plan:
            raise EditError("Choose at least one page to keep.")

        output = fitz.open()
        try:
            template = document[0].rect if document.page_count else fitz.paper_rect("a4")
            for item in plan:
                if item.get("blank"):
                    output.new_page(width=template.width, height=template.height)
                    continue

                source_page = int(item.get("source") or 0) - 1
                if source_page < 0 or source_page >= document.page_count:
                    raise EditError("That page plan refers to a page that is not in this PDF.")

                output.insert_pdf(
                    document,
                    from_page=source_page,
                    to_page=source_page,
                    links=1,
                    annots=1,
                    widgets=1,
                )
                rotation = int(item.get("rotate") or 0) % 360
                if rotation not in {0, 90, 180, 270}:
                    raise EditError("Page rotation must be a quarter turn.")
                output[-1].set_rotation(rotation)

            output.save(destination, garbage=4, deflate=True)
            return output.page_count
        finally:
            output.close()
    finally:
        document.close()


def find_font() -> str | None:
    for candidate in FONT_CANDIDATES:
        if Path(candidate).is_file():
            return candidate
    return None


def _page(document: fitz.Document, number: int) -> fitz.Page:
    index = int(number) - 1
    if index < 0 or index >= document.page_count:
        raise EditError("That edit refers to a page that is not in this PDF.")
    return document[index]


def _rect(values: list) -> fitz.Rect:
    if not isinstance(values, list) or len(values) != 4:
        raise EditError("An edit is missing its position on the page.")
    rect = fitz.Rect(*[float(value) for value in values])
    if rect.is_empty or rect.is_infinite:
        raise EditError("An edit has an empty area on the page.")
    return rect


def _union(rects: list[fitz.Rect]) -> fitz.Rect:
    united = fitz.Rect(rects[0])
    for rect in rects[1:]:
        united.include_rect(rect)
    return united


def _color(value: str | None) -> tuple[float, float, float]:
    hex_color = (value or "#111827").removeprefix("#")
    if len(hex_color) != 6:
        return (17 / 255, 24 / 255, 39 / 255)
    try:
        channels = [int(hex_color[index : index + 2], 16) / 255 for index in (0, 2, 4)]
    except ValueError:
        return (17 / 255, 24 / 255, 39 / 255)
    return (channels[0], channels[1], channels[2])


def _font_kwargs(fontfile: str | None) -> dict:
    if fontfile:
        return {"fontfile": fontfile, "fontname": "body"}
    return {"fontname": "helv"}


def _require_encodable(text: str, fontfile: str | None) -> None:
    if fontfile:
        return
    try:
        text.encode("latin-1")
    except UnicodeEncodeError as error:
        raise EditError("This text includes characters the server font cannot draw.") from error


def _fits(text: str, rect: fitz.Rect, fontsize: float, fontfile: str | None) -> bool:
    probe = fitz.open()
    try:
        page = probe.new_page(width=rect.width + 8, height=max(rect.height + 8, fontsize * 2))
        box = fitz.Rect(2, 2, 2 + rect.width, 2 + max(rect.height, fontsize))
        spare = page.insert_textbox(box, text, fontsize=fontsize, color=(0, 0, 0), **_font_kwargs(fontfile))
        return spare >= -1
    finally:
        probe.close()


def _preflight_replacements(replacements: list[dict], fontfile: str | None) -> None:
    for replacement in replacements:
        text = str(replacement.get("text") or "").strip()
        if text == "":
            raise EditError("Type the replacement sentence before saving.")
        _require_encodable(text, fontfile)
        rects = [_rect(raw).normalize() for raw in replacement.get("rects") or []]
        if not rects:
            raise EditError("Select the sentence you want to replace.")
        box = _union(rects)
        fontsize = float(replacement.get("size") or max(8, min(box.height * 0.8, 28)))
        fontsize = max(6, min(fontsize, 72))
        fitted = None
        for candidate in (fontsize, fontsize * 0.85, fontsize * 0.75):
            if _fits(text, box, candidate, fontfile):
                fitted = candidate
                break
        if fitted is None:
            raise EditError("The replacement is longer than the selected line. Shorten it and try again.")
        replacement["size"] = fitted
        replacement["text"] = text


def _redact(document: fitz.Document, spec: dict) -> bool:
    touched = False
    for replacement in spec.get("replacements") or []:
        page = _page(document, replacement.get("page") or 0)
        for raw in replacement.get("rects") or []:
            rect = _rect(raw).normalize() + (-0.4, -0.4, 0.4, 0.4)
            page.add_redact_annot(rect, fill=None, cross_out=False)
            touched = True
    for redaction in spec.get("redactions") or []:
        page = _page(document, redaction.get("page") or 0)
        page.add_redact_annot(_rect(redaction.get("rect")), fill=(1, 1, 1), cross_out=False)
        touched = True
    if not touched:
        return False
    for page in document:
        page.apply_redactions(
            images=fitz.PDF_REDACT_IMAGE_NONE,
            graphics=fitz.PDF_REDACT_LINE_ART_NONE,
            text=fitz.PDF_REDACT_TEXT_REMOVE,
        )
    return True


def _covers(document: fitz.Document, covers: list[dict]) -> None:
    for cover in covers:
        page = _page(document, cover.get("page") or 0)
        page.draw_rect(_rect(cover.get("rect")), color=None, fill=(1, 1, 1), width=0, overlay=True)


def _write_replacements(document: fitz.Document, replacements: list[dict], fontfile: str | None) -> None:
    for replacement in replacements:
        page = _page(document, replacement.get("page") or 0)
        box = _union([_rect(raw).normalize() for raw in replacement.get("rects") or []])
        fontsize = float(replacement.get("size") or 12)
        if box.height < fontsize * 1.15:
            box.y1 = box.y0 + fontsize * 1.25
        spare = page.insert_textbox(
            box,
            replacement["text"],
            fontsize=fontsize,
            color=(0.07, 0.09, 0.15),
            overlay=True,
            **_font_kwargs(fontfile),
        )
        if spare < -1:
            raise EditError("The replacement is longer than the selected line. Shorten it and try again.")


def _write_texts(document: fitz.Document, texts: list[dict], fontfile: str | None) -> None:
    for item in texts:
        text = str(item.get("text") or "").strip()
        if text == "":
            continue
        _require_encodable(text, fontfile)
        page = _page(document, item.get("page") or 0)
        box = _rect(item.get("rect"))
        fontsize = max(6, min(float(item.get("size") or 12), 72))
        page.insert_textbox(
            box,
            text,
            fontsize=fontsize,
            color=_color(item.get("color")),
            overlay=True,
            **_font_kwargs(fontfile),
        )


def _images(document: fitz.Document, images: list[dict]) -> None:
    for image in images:
        path = Path(str(image.get("path") or ""))
        if not path.is_file():
            raise EditError("An image for this edit could not be found.")
        page = _page(document, image.get("page") or 0)
        page.insert_image(_rect(image.get("rect")), filename=str(path), keep_proportion=True, overlay=True)


def _highlights(document: fitz.Document, highlights: list[dict]) -> None:
    for highlight in highlights:
        page = _page(document, highlight.get("page") or 0)
        rects = [_rect(raw).normalize() for raw in highlight.get("rects") or []]
        if not rects:
            continue
        quads = [fitz.Quad(rect.tl, rect.tr, rect.bl, rect.br) for rect in rects]
        annot = page.add_highlight_annot(quads)
        annot.set_opacity(0.45)
        annot.update()


def _fields(document: fitz.Document, fields: list[dict]) -> None:
    pending: dict[str, object] = {}
    for field in fields:
        name = str(field.get("name") or "")
        if name != "":
            pending[name] = field.get("value")
    if not pending:
        return

    # Update each widget before walking to the next page. Holding widget
    # objects across pages unbinds them in PyMuPDF.
    for index in range(document.page_count):
        page = document[index]
        widget = page.first_widget
        while widget:
            nxt = widget.next
            name = widget.field_name or ""
            if name in pending:
                kind = (widget.field_type_string or "").lower()
                _assign_widget(widget, pending[name])
                if "radio" not in kind:
                    pending.pop(name, None)
            widget = nxt


def _assign_widget(widget: fitz.Widget, value: object) -> None:
    kind = (widget.field_type_string or "").lower()
    if "check" in kind:
        widget.field_value = value is True or value in {1, "1", "Yes", "On", "true", "True"}
        widget.update()
        return
    if "radio" in kind:
        state = widget.on_state()
        if str(value) == str(state):
            widget.field_value = state
            widget.update()
        return
    widget.field_value = "" if value is None else str(value)
    widget.update()


if __name__ == "__main__":
    sys.exit(main(sys.argv))
