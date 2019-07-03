-- Add a table for payout method types
create table sd_payout_method_types (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    title varchar(128) NOT NULL,
    PRIMARY KEY(id)
) ENGINE=InnoDB;

alter table sd_payout_requests add constraint
    sd_payout_request_type_fk
    foreign key (type_id)
    references sd_payout_method_types (id);

insert into sd_payout_method_types (id, title) values
    (1, 'Stripe'),
    (2, 'PayPal');
