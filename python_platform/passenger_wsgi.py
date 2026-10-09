"""
Kingsway AI Platform - WSGI entry point.

DirectAdmin Python Selector (Passenger) deployment:
  Application root:      /home/<user>/python_apps/kingsway_ai
  Application startup:   passenger_wsgi.py
  WSGI callable:         application
  Environment variables: KINGSWAY_AI_SECRET, plus any AI_* provider vars.
  Optional read-model worker: KINGSWAY_PYTHON_READ_MODELS_ENABLED,
  KINGSWAY_DB_HOST/PORT/USER/PASSWORD/MASTER_SCHEMA/READS_SCHEMA, and
  KINGSWAY_DB_SSL_CA for remote database TLS. The database account should be
  limited to SELECT on the master sources and read-model DDL/DML on the reads
  schema. Read-model settings are independent from the AI provider settings.
"""

from app import create_app

application = create_app()
