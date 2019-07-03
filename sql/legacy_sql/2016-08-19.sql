-- For TD-224 
insert into sd_profile_payment_types (id, title) values
    (3, 'Monthly: Full Up-Front Payment Required, Full First Month Payment'),
    (4, 'Monthly: Full Up-Front Payment Required, Skip First Month Payment');

alter table sd_subscriptions add column last_paid_on datetime;

