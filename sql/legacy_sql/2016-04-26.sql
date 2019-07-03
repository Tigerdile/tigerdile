-- Create profile payment types table
create table sd_profile_payment_types (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    title varchar(256),
    PRIMARY KEY(id)
) ENGINE=InnoDB;

insert into sd_profile_payment_types (id, title) values
    (1, 'Monthly: Pro-Rated Up-Front Payment Required'),
    (2, 'Monthly: No Up-Front Payment');
    

-- Add a payment type column to profiles
alter table sd_profiles
    add column payment_type_id bigint(20) unsigned default 1,
    add constraint sd_profiles_payment_type_fk
    foreign key (payment_type_id)
    references sd_profile_payment_types(id);

-- add declined_on date to subscriptions
alter table sd_subscriptions
    add column declined_on datetime;

create index sd_subscriptions_declined_on_idx
    on sd_subscriptions (declined_on);

