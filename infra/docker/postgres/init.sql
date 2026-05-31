-- Enable required PostgreSQL extensions
CREATE EXTENSION IF NOT EXISTS postgis;
CREATE EXTENSION IF NOT EXISTS postgis_topology;
CREATE EXTENSION IF NOT EXISTS vector;
CREATE EXTENSION IF NOT EXISTS btree_gist;

-- TimescaleDB is optional in dev (not in this base image).
-- Uncomment if using timescale/timescaledb-ha:pg16 image:
-- CREATE EXTENSION IF NOT EXISTS timescaledb;
