-- Fix class_streams trigger recursion/conflicts.
-- These triggers attempted to UPDATE class_streams from class_streams triggers,
-- causing MySQL error 1442 during stream create/update workflows.
--
-- Application-level logic now handles:
-- - deactivating auto-generated default streams on custom stream creation
-- - reactivating default stream when all streams are inactive

USE KingsWayAcademy;

DROP TRIGGER IF EXISTS trg_manage_default_stream_on_insert;
DROP TRIGGER IF EXISTS trg_manage_default_stream_on_delete;

