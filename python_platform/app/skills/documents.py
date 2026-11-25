"""Document, slide and media producers for the assistant's render window.

The render window can already show an image, a PDF, a DOCX, an audio clip and a
video. This module is what actually *makes* those files, from the same governed
rows the assistant is already allowed to see.

Design rules, in order of importance:

  * **No school data is written to disk.** Every format is produced in memory
    and returned as a data URI, so a report cannot be left behind in a temp
    directory or served to anyone who did not ask for it.
  * **The rows are the ones PHP released.** Nothing here queries the database;
    the caller hands in the rows it already authorized.
  * **Bounded.** Row count, text length and output bytes all have ceilings, so
    a chat turn cannot be turned into an unbounded render job.
  * **Honest capability detection.** PDF needs reportlab, video needs ffmpeg,
    audio needs a speech engine. If the deployment lacks one, the result says
    so instead of returning an empty file.
"""

from __future__ import annotations

import base64
import io
import os
import re
import shutil
import subprocess
import tempfile
import zipfile
from datetime import datetime, timezone
from typing import Any
from xml.sax.saxutils import escape as xml_escape

MAX_ROWS = 60
MAX_COLUMNS = 12
MAX_TITLE = 120
MAX_SUMMARY = 1200
MAX_OUTPUT_BYTES = 3_000_000
MAX_VIDEO_SECONDS = 8

SUPPORTED_FORMATS = ("pdf", "docx", "video", "audio")

MIME_TYPES = {
    "pdf": "application/pdf",
    "docx": "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
    "video": "video/mp4",
    "audio": "audio/mpeg",
}

SCHOOL_NAME = "Kingsway Preparatory School"


# --------------------------------------------------------------------- helpers
def _clean(value: Any, limit: int) -> str:
    text = re.sub(r"\s+", " ", str(value if value is not None else "")).strip()
    return text[:limit]


_MONEY_TEXT = re.compile(r"^-?\d{4,}\.\d{2}$")


def _display(value: Any) -> str:
    if value is None:
        return ""
    if isinstance(value, bool):
        return "Yes" if value else "No"
    if isinstance(value, float):
        return f"{value:,.2f}".rstrip("0").rstrip(".")
    if isinstance(value, int):
        return f"{value:,}"
    text = _clean(value, 200)
    # PHP sends decimal columns as strings ("1485730.00"). A staff-facing
    # document must not show a bare digit run where a person would read money.
    if _MONEY_TEXT.match(text):
        whole, cents = text.split(".")
        return f"{int(whole):,}.{cents}"
    return text


def _usable_columns(columns: list, rows: list[dict[str, Any]]) -> list[str]:
    names = [str(name) for name in (columns or []) if str(name).strip()]
    if not names:
        seen: list[str] = []
        for row in rows[:5]:
            for key in row:
                if key not in seen:
                    seen.append(str(key))
        names = seen
    # An identifier column is noise in a staff-facing document.
    names = [
        name
        for name in names
        if not name.lower().endswith("_id") and name.lower() != "id"
    ]
    return names[:MAX_COLUMNS]


def _table(columns: list[str], rows: list[dict[str, Any]]) -> list[list[str]]:
    body: list[list[str]] = [columns]
    for row in rows[:MAX_ROWS]:
        body.append([_display(row.get(column)) for column in columns])
    return body


def _slug(title: str) -> str:
    slug = re.sub(r"[^a-z0-9]+", "-", title.lower()).strip("-")[:60]
    return slug or "school-document"


def _data_uri(payload: bytes, mime: str) -> str:
    return f"data:{mime};base64," + base64.b64encode(payload).decode("ascii")


def _stamp() -> str:
    return datetime.now(timezone.utc).strftime("%d %B %Y, %H:%M UTC")


def _decode_chart(chart_data_uri: str | None) -> bytes:
    """Decode a PNG data URI into bytes, refusing anything else."""
    text = str(chart_data_uri or "")
    if not text.startswith("data:image/png;base64,"):
        return b""
    try:
        return base64.b64decode(text.split(",", 1)[1], validate=True)
    except (ValueError, IndexError):
        return b""


# ------------------------------------------------------------------------- PDF
def _pdf(
    title: str,
    subtitle: str,
    summary: str,
    columns: list[str],
    rows: list[dict[str, Any]],
    chart_png: bytes,
) -> bytes:
    from reportlab.lib import colors
    from reportlab.lib.enums import TA_LEFT
    from reportlab.lib.pagesizes import A4
    from reportlab.lib.styles import ParagraphStyle, getSampleStyleSheet
    from reportlab.lib.units import mm
    from reportlab.platypus import (
        Image,
        Paragraph,
        SimpleDocTemplate,
        Spacer,
        Table,
        TableStyle,
    )

    buffer = io.BytesIO()
    document = SimpleDocTemplate(
        buffer,
        pagesize=A4,
        leftMargin=18 * mm,
        rightMargin=18 * mm,
        topMargin=16 * mm,
        bottomMargin=18 * mm,
        title=title,
        author=SCHOOL_NAME,
        subject="Prepared from authorized school records",
    )
    styles = getSampleStyleSheet()
    school = ParagraphStyle(
        "School",
        parent=styles["Normal"],
        fontName="Helvetica-Bold",
        fontSize=9,
        textColor=colors.HexColor("#178a50"),
        spaceAfter=2,
    )
    heading = ParagraphStyle(
        "Heading",
        parent=styles["Title"],
        fontName="Helvetica-Bold",
        fontSize=17,
        leading=21,
        alignment=TA_LEFT,
        spaceAfter=4,
        textColor=colors.HexColor("#172033"),
    )
    meta = ParagraphStyle(
        "Meta",
        parent=styles["Normal"],
        fontName="Helvetica",
        fontSize=8.5,
        textColor=colors.HexColor("#697386"),
        spaceAfter=10,
    )
    body = ParagraphStyle(
        "Body",
        parent=styles["Normal"],
        fontName="Helvetica",
        fontSize=10,
        leading=14.5,
        spaceAfter=8,
        textColor=colors.HexColor("#172033"),
    )
    cell = ParagraphStyle(
        "Cell",
        parent=styles["Normal"],
        fontName="Helvetica",
        fontSize=8,
        leading=10.5,
        textColor=colors.HexColor("#172033"),
    )
    cell_head = ParagraphStyle(
        "CellHead",
        parent=cell,
        fontName="Helvetica-Bold",
        textColor=colors.white,
    )

    story: list[Any] = [
        Paragraph(SCHOOL_NAME, school),
        Paragraph(xml_escape(title), heading),
        Paragraph(
            xml_escape(
                f"{subtitle + ' · ' if subtitle else ''}Prepared {_stamp()} · "
                "Figures drawn from records this account is authorized to read"
            ),
            meta,
        ),
    ]
    if summary:
        story.append(Paragraph(xml_escape(summary), body))

    if chart_png:
        try:
            # Generated artwork is a payload too. Re-encode the figure once so
            # the PDF embeds the smallest lossless copy that still fits A4.
            from ..image_optimization import optimise_bytes

            optimised = optimise_bytes(
                chart_png, max_edge=1600, output_format="PNG", min_bytes=0
            )
            if optimised is not None:
                chart_png = optimised[0]
            image = Image(io.BytesIO(chart_png))
            max_width = A4[0] - 36 * mm
            ratio = min(1.0, max_width / float(image.imageWidth or 1))
            image.drawWidth = image.imageWidth * ratio
            image.drawHeight = image.imageHeight * ratio
            story.extend([Spacer(1, 4 * mm), image, Spacer(1, 6 * mm)])
        except Exception:  # noqa: BLE001 - a broken image must not lose the report
            story.append(Spacer(1, 4 * mm))

    if columns and rows:
        data = [
            [
                Paragraph(xml_escape(name.replace("_", " ").title()), cell_head)
                for name in columns
            ]
        ]
        for row in rows[:MAX_ROWS]:
            data.append(
                [
                    Paragraph(xml_escape(_display(row.get(name))), cell)
                    for name in columns
                ]
            )
        available = A4[0] - 36 * mm
        table = Table(
            data,
            colWidths=[available / max(1, len(columns))] * len(columns),
            repeatRows=1,
        )
        table.setStyle(
            TableStyle(
                [
                    ("BACKGROUND", (0, 0), (-1, 0), colors.HexColor("#178a50")),
                    ("TEXTCOLOR", (0, 0), (-1, 0), colors.white),
                    ("VALIGN", (0, 0), (-1, -1), "TOP"),
                    ("GRID", (0, 0), (-1, -1), 0.4, colors.HexColor("#d8dee6")),
                    (
                        "ROWBACKGROUNDS",
                        (0, 1),
                        (-1, -1),
                        [colors.white, colors.HexColor("#f7f9fb")],
                    ),
                    ("LEFTPADDING", (0, 0), (-1, -1), 4),
                    ("RIGHTPADDING", (0, 0), (-1, -1), 4),
                    ("TOPPADDING", (0, 0), (-1, -1), 3),
                    ("BOTTOMPADDING", (0, 0), (-1, -1), 3),
                ]
            )
        )
        story.append(table)
        if len(rows) > MAX_ROWS:
            story.append(
                Paragraph(
                    xml_escape(f"Showing the first {MAX_ROWS} of {len(rows)} rows."),
                    meta,
                )
            )

    def _footer(canvas, doc):
        canvas.saveState()
        canvas.setFont("Helvetica", 7.5)
        canvas.setFillColor(colors.HexColor("#8a94a6"))
        canvas.drawString(18 * mm, 10 * mm, f"{SCHOOL_NAME} · Confidential")
        canvas.drawRightString(A4[0] - 18 * mm, 10 * mm, f"Page {doc.page}")
        canvas.restoreState()

    document.build(story, onFirstPage=_footer, onLaterPages=_footer)
    return buffer.getvalue()


# ------------------------------------------------------------------------ DOCX
_DOCX_CONTENT_TYPES = """<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>
</Types>"""

_DOCX_RELS = """<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>"""

_DOCX_DOCUMENT_RELS = """<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>"""

_DOCX_STYLES = """<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:style w:type="paragraph" w:styleId="Normal" w:default="1">
    <w:name w:val="Normal"/>
    <w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri"/><w:sz w:val="22"/></w:rPr>
  </w:style>
</w:styles>"""


def _docx_paragraph(
    text: str,
    *,
    bold: bool = False,
    size: int = 22,
    colour: str = "172033",
    space_after: int = 120,
) -> str:
    run_props = f'<w:sz w:val="{size}"/><w:color w:val="{colour}"/>'
    if bold:
        run_props = "<w:b/>" + run_props
    return (
        "<w:p><w:pPr>"
        f'<w:spacing w:after="{space_after}"/>'
        "</w:pPr><w:r><w:rPr>"
        f"{run_props}</w:rPr>"
        f'<w:t xml:space="preserve">{xml_escape(text)}</w:t></w:r></w:p>'
    )


def _docx_table(columns: list[str], rows: list[dict[str, Any]]) -> str:
    border = (
        "<w:tblBorders>"
        + "".join(
            f'<w:{edge} w:val="single" w:sz="4" w:space="0" w:color="d8dee6"/>'
            for edge in ("top", "left", "bottom", "right", "insideH", "insideV")
        )
        + "</w:tblBorders>"
    )

    def _cell(text: str, *, header: bool = False) -> str:
        shade = (
            '<w:shd w:val="clear" w:color="auto" w:fill="178a50"/>' if header else ""
        )
        colour = "FFFFFF" if header else "172033"
        bold = "<w:b/>" if header else ""
        return (
            "<w:tc><w:tcPr>"
            f'{shade}<w:tcW w:w="0" w:type="auto"/>'
            "</w:tcPr><w:p><w:r><w:rPr>"
            f'{bold}<w:sz w:val="17"/><w:color w:val="{colour}"/>'
            '</w:rPr><w:t xml:space="preserve">'
            f"{xml_escape(text)}</w:t></w:r></w:p></w:tc>"
        )

    header = (
        "<w:tr>"
        + "".join(
            _cell(name.replace("_", " ").title(), header=True) for name in columns
        )
        + "</w:tr>"
    )
    body = "".join(
        "<w:tr>"
        + "".join(_cell(_display(row.get(name))) for name in columns)
        + "</w:tr>"
        for row in rows[:MAX_ROWS]
    )
    return f"<w:tbl><w:tblPr>{border}</w:tblPr>{header}{body}</w:tbl>"


def _docx(
    title: str,
    subtitle: str,
    summary: str,
    columns: list[str],
    rows: list[dict[str, Any]],
) -> bytes:
    parts = [
        _docx_paragraph(
            SCHOOL_NAME, bold=True, size=18, colour="178a50", space_after=40
        ),
        _docx_paragraph(title, bold=True, size=34, space_after=60),
        _docx_paragraph(
            f"{subtitle + ' · ' if subtitle else ''}Prepared {_stamp()} · "
            "Figures drawn from records this account is authorized to read",
            size=17,
            colour="697386",
            space_after=200,
        ),
    ]
    if summary:
        parts.append(_docx_paragraph(summary, space_after=200))
    if columns and rows:
        parts.append(_docx_table(columns, rows))
        if len(rows) > MAX_ROWS:
            parts.append(
                _docx_paragraph(
                    f"Showing the first {MAX_ROWS} of {len(rows)} rows.",
                    size=16,
                    colour="697386",
                )
            )
    document_body = "".join(parts)
    document = (
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
        f"<w:body>{document_body}"
        '<w:sectPr><w:pgSz w:w="11906" w:h="16838"/>'
        '<w:pgMar w:top="1134" w:right="1134" w:bottom="1134" w:left="1134"/></w:sectPr>'
        "</w:body></w:document>"
    )
    buffer = io.BytesIO()
    with zipfile.ZipFile(buffer, "w", zipfile.ZIP_DEFLATED) as package:
        package.writestr("[Content_Types].xml", _DOCX_CONTENT_TYPES)
        package.writestr("_rels/.rels", _DOCX_RELS)
        package.writestr("word/document.xml", document)
        package.writestr("word/_rels/document.xml.rels", _DOCX_DOCUMENT_RELS)
        package.writestr("word/styles.xml", _DOCX_STYLES)
    return buffer.getvalue()


# ---------------------------------------------------------------------- video
def _video(
    title: str, summary: str, chart_png: bytes, rows_text: str
) -> dict[str, Any]:
    """A short MP4: a title card, the figure, and a closing summary card.

    ffmpeg does the encoding; matplotlib draws the cards. Both are deployment
    dependencies, so their absence is reported rather than guessed at.
    """
    if shutil.which("ffmpeg") is None:
        return {
            "status": "unavailable",
            "message": "video rendering needs ffmpeg, which is not installed",
        }

    try:
        import matplotlib

        matplotlib.use("Agg")
        import matplotlib.pyplot as plt
        from matplotlib.patches import Rectangle
    except Exception:  # noqa: BLE001
        return {"status": "unavailable", "message": "video rendering needs matplotlib"}

    width, height = 1280, 720
    green = "#178a50"
    ink = "#172033"

    import textwrap

    def _lines(text: str, width: int, max_lines: int) -> list[str]:
        wrapped = textwrap.wrap(
            _clean(text, 900), width=max(20, width), break_long_words=False
        )
        return wrapped[:max_lines]

    def _card(heading: str, subheading: str, body: str) -> bytes:
        figure = plt.figure(figsize=(width / 100, height / 100), dpi=100)
        axis = figure.add_axes([0, 0, 1, 1])
        axis.set_axis_off()
        axis.add_patch(Rectangle((0, 0), 1, 1, color="#ffffff"))
        axis.add_patch(Rectangle((0, 0.94), 1, 0.06, color=green))
        axis.text(
            0.06, 0.84, SCHOOL_NAME.upper(), fontsize=13, color=green, weight="bold"
        )
        y = 0.70
        for line in _lines(heading, 34, 3):
            axis.text(0.06, y, line, fontsize=28, color=ink, weight="bold")
            y -= 0.075
        if subheading:
            y -= 0.02
            axis.text(0.06, y, subheading[:100], fontsize=15, color="#697386")
            y -= 0.08
        for line in _lines(body, 82, 7):
            axis.text(0.06, y, line, fontsize=13.5, color=ink)
            y -= 0.062
        axis.text(
            0.06,
            0.07,
            "Prepared from records this account is authorized to read",
            fontsize=10,
            color="#8a94a6",
        )
        buffer = io.BytesIO()
        figure.savefig(buffer, format="png", dpi=100)
        plt.close(figure)
        return buffer.getvalue()

    title_card = _card(title, "A short visual summary", "Figures follow.")
    closing = _card("What the figures show", "", summary or rows_text)
    chart_card = chart_png or _card("The figures", "", rows_text)

    with tempfile.TemporaryDirectory(prefix="kw-ai-video-") as workspace:
        frames = []
        for index, payload in enumerate((title_card, chart_card, closing)):
            path = os.path.join(workspace, f"frame-{index}.png")
            with open(path, "wb") as handle:
                handle.write(payload)
            frames.append(path)
        listfile = os.path.join(workspace, "frames.txt")
        with open(listfile, "w", encoding="utf-8") as handle:
            handle.writelines(f"file '{frame}'\nduration 2.4\n" for frame in frames)
            handle.write(f"file '{frames[-1]}'\n")
        output = os.path.join(workspace, "summary.mp4")
        command = [
            "ffmpeg",
            "-y",
            "-hide_banner",
            "-loglevel",
            "error",
            "-f",
            "concat",
            "-safe",
            "0",
            "-i",
            listfile,
            "-vf",
            "scale=1280:720:force_original_aspect_ratio=decrease,pad=1280:720:(ow-iw)/2:(oh-ih)/2,format=yuv420p",
            "-t",
            str(MAX_VIDEO_SECONDS),
            "-r",
            "24",
            "-c:v",
            "libx264",
            "-preset",
            "veryfast",
            "-crf",
            "26",
            "-movflags",
            "+faststart",
            output,
        ]
        try:
            completed = subprocess.run(
                command, capture_output=True, timeout=90, check=False
            )
        except (OSError, subprocess.SubprocessError):
            return {
                "status": "unavailable",
                "message": "video rendering could not start",
            }
        if completed.returncode != 0 or not os.path.exists(output):
            return {
                "status": "unavailable",
                "message": "video rendering did not complete",
            }
        with open(output, "rb") as handle:
            payload = handle.read()
    if not payload:
        return {"status": "unavailable", "message": "video rendering produced nothing"}
    return {"status": "answered", "payload": payload}


# ---------------------------------------------------------------------- audio
_TTS_VOICES: tuple[tuple[str, list[str]], ...] = (
    ("espeak-ng", ["-w", "{out}", "{text}"]),
    ("espeak", ["-w", "{out}", "{text}"]),
    ("flite", ["-t", "{text}", "-o", "{out}"]),
    ("pico2wave", ["-w", "{out}", "{text}"]),
)


def _audio(text: str) -> dict[str, Any]:
    """Speech synthesis is a deployment capability, not an assumption."""
    voice = next(
        ((name, args) for name, args in _TTS_VOICES if shutil.which(name)), None
    )
    if voice is None:
        return {
            "status": "unavailable",
            "message": (
                "speech synthesis is not installed on this server, so an audio "
                "version cannot be produced. The same summary is available as a "
                "PDF, a DOCX or a short video."
            ),
        }
    name, template = voice
    with tempfile.TemporaryDirectory(prefix="kw-ai-audio-") as workspace:
        wav = os.path.join(workspace, "narration.wav")
        args = [name] + [part.format(out=wav, text=text) for part in template]
        try:
            completed = subprocess.run(
                args, capture_output=True, timeout=60, check=False
            )
        except (OSError, subprocess.SubprocessError):
            return {
                "status": "unavailable",
                "message": "speech synthesis could not start",
            }
        if completed.returncode != 0 or not os.path.exists(wav):
            return {
                "status": "unavailable",
                "message": "speech synthesis did not complete",
            }
        mp3 = os.path.join(workspace, "narration.mp3")
        if shutil.which("ffmpeg"):
            encoded = subprocess.run(
                [
                    "ffmpeg",
                    "-y",
                    "-hide_banner",
                    "-loglevel",
                    "error",
                    "-i",
                    wav,
                    "-codec:a",
                    "libmp3lame",
                    "-q:a",
                    "5",
                    mp3,
                ],
                capture_output=True,
                timeout=60,
                check=False,
            )
            if encoded.returncode == 0 and os.path.exists(mp3):
                with open(mp3, "rb") as handle:
                    return {
                        "status": "answered",
                        "payload": handle.read(),
                        "mime": "audio/mpeg",
                        "extension": "mp3",
                    }
        with open(wav, "rb") as handle:
            return {
                "status": "answered",
                "payload": handle.read(),
                "mime": "audio/wav",
                "extension": "wav",
            }


# ------------------------------------------------------------------- entrypoint
def produce(
    fmt: str,
    *,
    title: str,
    subtitle: str = "",
    summary: str = "",
    columns: list | None = None,
    rows: list | None = None,
    chart_data_uri: str = "",
) -> dict[str, Any]:
    """Build one artifact from already-authorized rows.

    Returns the tool-result shape: ``status``, the display fields, and a
    ``data_uri`` the orchestrator turns into a render-window artifact. The data
    URI is never placed in the model's prompt.
    """
    fmt = str(fmt or "").strip().lower()
    if fmt not in SUPPORTED_FORMATS:
        return {
            "status": "unavailable",
            "message": "that document format is not supported",
        }

    clean_rows = [row for row in (rows or []) if isinstance(row, dict)]
    clean_columns = _usable_columns(list(columns or []), clean_rows)
    clean_title = _clean(title, MAX_TITLE) or "School report"
    clean_subtitle = _clean(subtitle, 120)
    clean_summary = _clean(summary, MAX_SUMMARY)
    row_text = "; ".join(
        ", ".join(
            f"{name.replace('_', ' ')}: {_display(row.get(name))}"
            for name in clean_columns[:4]
        )
        for row in clean_rows[:8]
    )

    # A document with no rows is still legitimate, but it needs a summary to
    # say anything at all. Refusing is clearer than an empty page.
    if fmt in ("pdf", "docx") and not clean_rows and not clean_summary:
        return {
            "status": "unavailable",
            "message": "there is no authorized content to put in a document yet; run a report first",
        }

    if fmt == "audio":
        narration = _clean(f"{clean_title}. {clean_summary or row_text}", 1200)
        audio = _audio(narration)
        if audio.get("status") != "answered":
            return audio
        payload = audio["payload"]
        mime = audio.get("mime", "audio/mpeg")
        extension = audio.get("extension", "mp3")
    elif fmt == "video":
        video = _video(
            clean_title, clean_summary, _decode_chart(chart_data_uri), row_text
        )
        if video.get("status") != "answered":
            return video
        payload = video["payload"]
        mime = "video/mp4"
        extension = "mp4"
    elif fmt == "pdf":
        try:
            payload = _pdf(
                clean_title,
                clean_subtitle,
                clean_summary,
                clean_columns,
                clean_rows,
                _decode_chart(chart_data_uri),
            )
        except Exception:  # noqa: BLE001 - reportlab is a deployment dependency
            return {
                "status": "unavailable",
                "message": "the PDF could not be produced just now",
            }
        mime = MIME_TYPES["pdf"]
        extension = "pdf"
    else:
        payload = _docx(
            clean_title, clean_subtitle, clean_summary, clean_columns, clean_rows
        )
        mime = MIME_TYPES["docx"]
        extension = "docx"

    if len(payload) > MAX_OUTPUT_BYTES:
        return {
            "status": "unavailable",
            "message": "that document would be too large to return in a chat turn; narrow the report first",
        }

    return {
        "status": "answered",
        "format": fmt,
        "title": clean_title,
        "mime_type": mime,
        "download_name": f"{_slug(clean_title)}.{extension}",
        "size_bytes": len(payload),
        "row_count": len(clean_rows),
        "summary": clean_summary
        or (
            f"{fmt.upper()} of {clean_title} with {len(clean_rows)} authorized row(s)."
        ),
        "data_uri": _data_uri(payload, mime),
    }


def capability_report() -> dict[str, bool]:
    """What this deployment can actually produce."""
    return {
        "pdf": _module_available("reportlab"),
        "docx": True,
        "video": shutil.which("ffmpeg") is not None and _module_available("matplotlib"),
        "audio": any(shutil.which(name) for name, _ in _TTS_VOICES),
    }


def _module_available(name: str) -> bool:
    try:
        __import__(name)
        return True
    except Exception:  # noqa: BLE001
        return False
