"""DirectAdmin Python Selector startup file.

The panel generates its own passenger_wsgi.py bootstrap, which loads THIS
file (the configured "Application startup file") and serves the callable
named `application` (the configured "Application Entry point").

DirectAdmin form values:
    Application startup file:  wsgi.py
    Application Entry point:   application

Do NOT set the startup file to passenger_wsgi.py - that is the panel's
bootstrap and would make it load itself (RecursionError).
"""

import os
import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

from app import create_app

application = create_app()
