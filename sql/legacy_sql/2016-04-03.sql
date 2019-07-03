-- Add a column to tiers table to track maximum allocation.
alter table sd_tiers add column max_available integer;
