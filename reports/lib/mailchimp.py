"""Mailchimp Marketing API client.

Reads MAILCHIMP_API_KEY and MAILCHIMP_DC from the repo .env (both gitignored).
"""
from __future__ import annotations

import os

import mailchimp_marketing as mc
from dotenv import load_dotenv

from .io import PROJECT_ROOT


def client() -> mc.Client:
    load_dotenv(PROJECT_ROOT / ".env")
    c = mc.Client()
    c.set_config({
        "api_key": os.environ["MAILCHIMP_API_KEY"],
        "server": os.environ["MAILCHIMP_DC"],
    })
    return c
