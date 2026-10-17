"""SMTP fallback delivery executed in the Python platform.

Resilience path only: PHP's PHPMailer owns all normal email delivery; when a
message has exhausted its PHP SMTP retries (or the PHP SMTP transport is
unusable), PHP may hand the already-rendered message here. This module uses
:mod:`smtplib` from the standard library, mirroring the normalization
contract of the other bridges:

    {"ok": bool, "sent": int, "failed": int, "message": str, "transport": str}
"""

from __future__ import annotations

import smtplib
import ssl
from email.message import EmailMessage
from email.utils import formataddr
from typing import Any

MAX_ATTACHMENTS = 10
MAX_ATTACHMENT_BYTES = 10 * 1024 * 1024
SMTP_TIMEOUT_SECONDS = 45


class EmailTransportError(RuntimeError):
    """Raised when the SMTP conversation cannot be completed."""


def send_email(payload: dict[str, Any]) -> dict[str, Any]:
    """Deliver one rendered message to its recipients over SMTP."""
    host = str(payload.get("host") or "").strip()
    port = int(payload.get("port") or 0)
    security = str(payload.get("security") or "tls").strip().lower()
    username = str(payload.get("username") or "")
    password = str(payload.get("password") or "")
    from_email = str(payload.get("from_email") or "").strip()
    from_name = str(payload.get("from_name") or "").strip()
    subject = str(payload.get("subject") or "")
    html = str(payload.get("html") or "")
    text = str(payload.get("text") or "")
    reply_to = str(payload.get("reply_to") or "").strip()

    recipients: list[str] = []
    for entry in payload.get("to") or []:
        address = str((entry or {}).get("email") or "").strip()
        if address != "" and address not in recipients:
            recipients.append(address)
    if host == "" or port <= 0 or not recipients or from_email == "":
        return _outcome(
            False, 0, len(recipients), "SMTP configuration or recipients are incomplete"
        )

    message = EmailMessage()
    message["Subject"] = subject
    message["From"] = formataddr((from_name, from_email)) if from_name else from_email
    message["To"] = ", ".join(recipients)
    if reply_to:
        message["Reply-To"] = reply_to
    message.set_content(text or _strip_html(html or ""))
    if html:
        message.add_alternative(html, subtype="html")

    attached = 0
    for attachment in (payload.get("attachments") or [])[:MAX_ATTACHMENTS]:
        path = str((attachment or {}).get("path") or "")
        try:
            import os

            if path == "" or not os.path.isfile(path):
                continue
            if os.path.getsize(path) > MAX_ATTACHMENT_BYTES:
                continue
            with open(path, "rb") as handle:
                data = handle.read(MAX_ATTACHMENT_BYTES)
        except OSError:
            continue
        file_name = str((attachment or {}).get("file_name") or path.rsplit("/", 1)[-1])
        maintype, _, subtype = str(
            (attachment or {}).get("mime_type") or "application/octet-stream"
        ).partition("/")
        message.add_attachment(
            data,
            maintype=maintype or "application",
            subtype=subtype or "octet-stream",
            filename=file_name,
        )
        attached += 1

    try:
        context = ssl.create_default_context()
        if security == "ssl":
            with smtplib.SMTP_SSL(
                host, port, timeout=SMTP_TIMEOUT_SECONDS, context=context
            ) as client:
                if username:
                    client.login(username, password)
                client.send_message(message)
        else:
            with smtplib.SMTP(host, port, timeout=SMTP_TIMEOUT_SECONDS) as client:
                client.ehlo()
                if security == "tls":
                    client.starttls(context=context)
                    client.ehlo()
                if username:
                    client.login(username, password)
                client.send_message(message)
    except (smtplib.SMTPException, OSError, ssl.SSLError) as error:
        return _outcome(
            False,
            0,
            len(recipients),
            f"SMTP fallback failed: {type(error).__name__}",
        )
    return {
        "ok": True,
        "sent": len(recipients),
        "failed": 0,
        "attachments": attached,
        "message": "Delivered through the Python SMTP fallback",
        "transport": "python-smtplib",
    }


def _outcome(ok: bool, sent: int, failed: int, message: str) -> dict[str, Any]:
    return {
        "ok": bool(ok),
        "sent": int(sent),
        "failed": int(failed),
        "message": message,
        "transport": "python-smtplib",
    }


def _strip_html(html: str) -> str:
    """Cheap text alternative for clients without HTML rendering."""
    import re

    text = re.sub(r"(?is)<(script|style).*?</\1>", " ", html)
    text = re.sub(r"(?s)<[^>]+>", " ", text)
    text = re.sub(r"\s+", " ", text)
    return text.strip()
