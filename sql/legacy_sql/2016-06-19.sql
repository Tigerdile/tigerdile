-- Add a column to store paypal destination.
alter table sd_payout_requests add column target varchar(512);

