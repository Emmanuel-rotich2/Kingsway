"""
Kingsway AI Platform - WSGI entry point.

DirectAdmin Python Selector (Passenger) deployment:
  Application root:      /home/<user>/python_apps/kingsway_ai
  Application startup:   passenger_wsgi.py
  WSGI callable:         application
  Environment variables: KINGSWAY_AI_SECRET, plus any AI_* provider vars
"""

from app import create_app

application = create_app()
