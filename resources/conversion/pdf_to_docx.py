#!/usr/bin/env python3
"""Hybrid PDF→DOCX conversion that preserves flowcharts and ERDs.

pdf2docx rebuilds text/tables well but shreds vector diagrams (flowcharts,
ER diagrams, architecture drawings) into broken shapes or nested fake tables.

Strategy:
1. Classify each page as document vs diagram using vector-drawing density.
2. Build a temporary PDF where diagram pages are replaced with a high-DPI
   raster of themselves (so pdf2docx embeds one clean image).
3. Convert that PDF with pdf2docx (text/table pages stay editable).

Classification is conservative: pages with substantial prose or table-like
cell text are never rasterized, so Word output remains editable.
"""

from __future__ import annotations

import argparse
import logging
import sys
import tempfile
from pathlib import Path

import fitz
from pdf2docx import Converter

logging.basicConfig(level=logging.INFO, format="%(levelname)s %(message)s")
logger = logging.getLogger("pdf_to_docx")

PDF2DOCX_SETTINGS = {
    "delete_end_line_hyphen": True,
    "multi_processing": False,
    # Sharper clips when pdf2docx still rasterizes residual vectors.
    "clip_image_res_ratio": 6.0,
    # Merge nearby vector pieces so a flowchart becomes one image, not dozens.
    "min_svg_gap_dx": 30.0,
    "min_svg_gap_dy": 20.0,
    "min_svg_w": 2.0,
    "min_svg_h": 2.0,
    # Lattice = real bordered tables. Stream tables mis-detect bullet lists and
    # scatter the Word layout, so keep them off.
    "parse_lattice_table": True,
    "parse_stream_table": False,
    "extract_stream_table": False,
    "list_not_table": True,
}


def _page_metrics(page: fitz.Page) -> dict[str, float | int]:
    drawings = page.get_drawings()
    page_area = abs(page.rect) or 1.0
    draw_area = 0.0
    for drawing in drawings:
        rect = drawing.get("rect")
        if rect is None:
            continue
        draw_area += abs(fitz.Rect(rect) & page.rect)
    text = page.get_text("text") or ""
    words = len(text.split())
    return {
        "drawings": len(drawings),
        "draw_ratio": min(draw_area / page_area, 1.0),
        "words": words,
        "chars": len(text.strip()),
        "images": len(page.get_images(full=True)),
    }


def is_diagram_page(page: fitz.Page) -> bool:
    """Only treat sparse, drawing-heavy pages as diagrams.

    Tables and prose often have many border lines; those must stay editable.
    """
    m = _page_metrics(page)
    drawings = int(m["drawings"])
    draw_ratio = float(m["draw_ratio"])
    words = int(m["words"])
    chars = int(m["chars"])

    # Substantial readable content → keep editable via pdf2docx.
    if words >= 160 or chars >= 800:
        return False

    # Table-like: many boxes but plenty of cell labels relative to drawings.
    if drawings >= 8 and words >= 60 and (words / max(drawings, 1)) >= 2.5:
        return False

    # Decorative borders / underlines on mostly-empty pages.
    if drawings <= 6:
        return False

    # Flowchart / ERD: many vectors, sparse labels.
    if drawings >= 18 and words < 100 and draw_ratio >= 0.04:
        return True
    if drawings >= 30 and words < 160:
        return True
    if drawings >= 12 and words < 40 and draw_ratio >= 0.06:
        return True

    return False


def classify_pages(pdf_path: Path) -> list[bool]:
    document = fitz.open(pdf_path)
    try:
        metrics = [_page_metrics(document[index]) for index in range(len(document))]
        flags = [is_diagram_page(document[index]) for index in range(len(document))]
    finally:
        document.close()

    diagram_count = sum(1 for flag in flags if flag)
    page_count = len(flags)

    # Safety: never rasterize an entire text-capable document away.
    if page_count > 0 and diagram_count == page_count:
        total_words = sum(int(metric["words"]) for metric in metrics)
        if total_words >= 80:
            logger.warning(
                "All %d page(s) looked diagram-like but contain %d words; "
                "keeping pages editable instead of rasterizing.",
                page_count,
                total_words,
            )
            return [False] * page_count

    return flags


def build_pdf_with_raster_diagrams(
    source_pdf: Path,
    output_pdf: Path,
    diagram_flags: list[bool],
    dpi: int,
) -> int:
    """Return how many pages were rasterized."""
    source = fitz.open(source_pdf)
    output = fitz.open()
    zoom = dpi / 72.0
    matrix = fitz.Matrix(zoom, zoom)
    rasterized = 0

    try:
        for index in range(len(source)):
            page = source[index]
            if not diagram_flags[index]:
                output.insert_pdf(source, from_page=index, to_page=index)
                continue

            pixmap = page.get_pixmap(matrix=matrix, alpha=False)
            new_page = output.new_page(
                width=page.rect.width,
                height=page.rect.height,
            )
            new_page.insert_image(new_page.rect, pixmap=pixmap)
            rasterized += 1
            logger.info(
                "Rasterized diagram page %d/%d at %d DPI",
                index + 1,
                len(source),
                dpi,
            )

        output_pdf.parent.mkdir(parents=True, exist_ok=True)
        output.save(output_pdf)
    finally:
        output.close()
        source.close()

    return rasterized


def convert_pdf_to_docx(pdf_path: Path, docx_path: Path, dpi: int = 220) -> None:
    diagram_flags = classify_pages(pdf_path)
    diagram_count = sum(1 for flag in diagram_flags if flag)
    logger.info(
        "Classified %d/%d page(s) as diagram/flowchart/ERD",
        diagram_count,
        len(diagram_flags),
    )

    convert_source = pdf_path
    temp_pdf: Path | None = None

    try:
        if diagram_count > 0:
            temp_dir = Path(tempfile.mkdtemp(prefix="pdf-diagram-"))
            temp_pdf = temp_dir / "diagram-normalized.pdf"
            build_pdf_with_raster_diagrams(pdf_path, temp_pdf, diagram_flags, dpi)
            convert_source = temp_pdf

        docx_path.parent.mkdir(parents=True, exist_ok=True)
        converter = Converter(str(convert_source))
        try:
            converter.convert(str(docx_path), **PDF2DOCX_SETTINGS)
        finally:
            converter.close()
    finally:
        if temp_pdf is not None:
            try:
                temp_pdf.unlink(missing_ok=True)
                temp_pdf.parent.rmdir()
            except OSError:
                pass


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("pdf", type=Path, help="Input PDF path")
    parser.add_argument("docx", type=Path, help="Output DOCX path")
    parser.add_argument(
        "--dpi",
        type=int,
        default=220,
        help="Raster DPI for diagram/flowchart/ERD pages (default: 220)",
    )
    args = parser.parse_args(argv)

    if not args.pdf.is_file():
        print(f"PDF not found: {args.pdf}", file=sys.stderr)
        return 1

    if args.dpi < 72 or args.dpi > 600:
        print("--dpi must be between 72 and 600", file=sys.stderr)
        return 1

    convert_pdf_to_docx(args.pdf, args.docx, dpi=args.dpi)
    print(f"Wrote {args.docx}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
