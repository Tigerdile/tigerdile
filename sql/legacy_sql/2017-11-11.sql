-- Let's store our items in a table, so we can later expand this out
-- to a proper store.

create table sd_items (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    title text NOT NULL,
    profile_id bigint(20) unsigned,
    price decimal(10,2) NOT NULL,
    PRIMARY KEY(id),
    FOREIGN KEY (profile_id) REFERENCES sd_profiles(id)
) ENGINE=InnoDB;

insert into sd_items (id, title, price) values 
    (1, '10 Viewer Limit Stream', 5.00),
    (2, 'Unlimited Viewer Stream', 10.00),
    (3, 'Unlimited Viewers and Perks!', 40.00);

alter table sd_order_items add column item_id bigint(20) unsigned;
alter table sd_order_items add constraint fk_item_id FOREIGN KEY (item_id) REFERENCES sd_items(id);
