-- Enable required PostgreSQL extensions
CREATE EXTENSION IF NOT EXISTS postgis;
CREATE EXTENSION IF NOT EXISTS postgis_topology;
CREATE EXTENSION IF NOT EXISTS vector;
CREATE EXTENSION IF NOT EXISTS btree_gist;

-- TimescaleDB (included in timescale/timescaledb-ha:pg16 image)
CREATE EXTENSION IF NOT EXISTS timescaledb;
