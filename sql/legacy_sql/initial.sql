-- Initial DB scheme for Swaggerdile
-- Designed to be imported alongside wordpress DB.

-- Profiles, the core of the whole system.
create table sd_profiles (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    title text NOT NULL,
    content longtext,
    owner_id bigint(20) unsigned NOT NULL,
    created datetime DEFAULT CURRENT_TIMESTAMP,
    is_visible tinyint(1) DEFAULT 0,
    is_nsfw tinyint(1) DEFAULT 0,
    historical_fee decimal(10,2),
    url varchar(128),
    PRIMARY KEY(id),
    UNIQUE KEY url (url),
    FULLTEXT KEY `sd_profiles_title_idx` (title),
    FULLTEXT KEY `sd_profiles_content_idx` (content),
    FULLTEXT KEY `sd_profiles_content_title_idx` (title, content),
    KEY `sd_profiles_owner_id_idx` (owner_id),
    KEY `sd_profiles_created_idx` (created)
) ENGINE=InnoDB;


-- Content type tables, for dynamic additions of new
-- content types.
create table sd_content_types (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    title varchar(64) NOT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB;

-- Create some initial content types.
insert into sd_content_types (id, title) values (1, 'Post');
insert into sd_content_types (id, title) values (2, 'File');
insert into sd_content_types (id, title) values (3, 'Directory');

-- Content, what people are paying to see.
create table sd_content (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    title text NOT NULL,
    type_id bigint(20) unsigned NOT NULL,
    author_id bigint(20) unsigned NOT NULL,
    content longtext,
    created datetime DEFAULT CURRENT_TIMESTAMP,
    updated datetime,
    profile_id bigint(20) unsigned NOT NULL,
    parent_id bigint(20) unsigned,
    is_sample tinyint(1) DEFAULT 0,
    is_comments_disabled tinyint(1) DEFAULT 0,
    is_never_historical tinyint(1) DEFAULT 0,
    mime_type varchar(128),
    ordering bigint(20) unsigned,
    PRIMARY KEY(id),
    FULLTEXT KEY `sd_content_title_idx` (title),
    KEY `sd_content_ordering_idx` (ordering),
    FOREIGN KEY (type_id) references sd_content_types(id),
    FOREIGN KEY (profile_id) references sd_profiles(id),
    KEY `sd_content_author_id_idx` (author_id)
) ENGINE=InnoDB;

-- Milestones for a given profile
create table sd_milestones (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    profile_id bigint(20) unsigned NOT NULL,
    title varchar(512) NOT NULL,
    content longtext,
    price decimal(10,2),
    PRIMARY KEY(id),
    FOREIGN KEY (profile_id) references sd_profiles(id)
) ENGINE=InnoDB;

-- Reward types, to implement reward type icons.
create table sd_reward_types (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    title varchar(128) NOT NULL,
    PRIMARY KEY(id)
) ENGINE=InnoDB;

-- Tiers
create table sd_tiers (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    profile_id bigint(20) unsigned NOT NULL,
    title varchar(512),
    content longtext,
    is_shippable tinyint(1) NOT NULL DEFAULT 0,
    price decimal(10,2),
    PRIMARY KEY(id),
    FOREIGN KEY(profile_id) references sd_profiles(id)
) ENGINE=InnoDB;

-- Linkage of content to tiers
create table sd_content_tiers_link (
    content_id bigint(20) unsigned NOT NULL,
    tier_id bigint(20) unsigned NOT NULL,
    PRIMARY KEY(content_id, tier_id),
    FOREIGN KEY(content_id) references sd_content(id),
    FOREIGN KEY(tier_id) references sd_tiers(id)
) ENGINE=InnoDB;

-- Linkage of tiers to reward types
create table sd_tier_reward_types (
    tier_id bigint(20) unsigned NOT NULL,
    reward_type_id bigint(20) unsigned NOT NULL,
    PRIMARY KEY (tier_id, reward_type_id),
    FOREIGN KEY (tier_id) references sd_tiers(id),
    FOREIGN KEY (reward_type_id) references sd_reward_types (id)
) ENGINE=InnoDB;

-- Comments table
create table sd_comments (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    parent_id bigint(20) unsigned,
    content longtext,
    author_id bigint(20) unsigned NOT NULL,
    content_id bigint(20) unsigned NOT NULL,
    is_deleted tinyint(1) default 0,
    created datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY(id),
    FOREIGN KEY(content_id) references sd_content(id),
    KEY `sd_comments_author_id_idx` (author_id)
) ENGINE=InnoDB;

-- Order history
create table sd_orders (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    created datetime DEFAULT CURRENT_TIMESTAMP,
    completed datetime,
    user_id bigint(20) unsigned NOT NULL,
    total_price decimal(10, 2) NOT NULL,
    is_prorate tinyint(1) NOT NULL DEFAULT 0,
    is_recurring tinyint(1) NOT NULL DEFAULT 0,
    full_price decimal(10, 2) NOT NULL,
    meta longtext,
    PRIMARY KEY(id),
    KEY `sd_orders_user_id_idx` (user_id)
) ENGINE=InnoDB;

-- Order items
create table sd_order_items (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    order_id bigint(20) unsigned NOT NULL,
    profile_id bigint(20) unsigned NOT NULL,
    tier_id bigint(20) unsigned,
    tier_price decimal(10, 2),
    extra_price decimal(10, 2),
    historical_price decimal(10, 2),
    PRIMARY KEY(id),
    FOREIGN KEY(order_id) references sd_orders(id),
    FOREIGN KEY(profile_id) references sd_profiles(id),
    FOREIGN KEY(tier_id) references sd_tiers(id)
) ENGINE=InnoDB;


-- Payment methods
create table sd_payment_methods (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    title varchar(128) NOT NULL,
    PRIMARY KEY(id)
) ENGINE=InnoDB;

-- Insert stripe
insert into sd_payment_methods (id, title) values (1, 'Stripe');

-- Store customer payment info
create table sd_user_payment_methods (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    payment_method_id bigint(20) unsigned NOT NULL,
    user_id bigint(20) unsigned NOT NULL,
    title varchar(128) NOT NULL,
    metadata text,
    PRIMARY KEY(id),
    FOREIGN KEY(payment_method_id) references sd_payment_methods(id),
    KEY `sd_upm_user_id_idx` (user_id)
) ENGINE=InnoDB;

-- Customer payment info may have "child records" (cards).  Store them
-- for referential integrity.
create table sd_child_payment_methods (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    user_payment_method_id bigint(20) unsigned NOT NULL,
    metadata varchar(512),
    PRIMARY KEY(id),
    FOREIGN KEY(user_payment_method_id) references sd_user_payment_methods(id),
    KEY `sd_cpm_metadata_idx` (metadata)
) ENGINE=InnoDB;

-- Subscriptions for book keeping
create table sd_subscriptions (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    user_id bigint(20) unsigned NOT NULL,
    profile_id bigint(20) unsigned NOT NULL,
    tier_id bigint(20) unsigned,
    created datetime DEFAULT CURRENT_TIMESTAMP,
    payment decimal(10, 2) NOT NULL,
    is_historical_paid tinyint(1) NOT NULL DEFAULT 0,
    is_active tinyint(1) NOT NULL,
    child_payment_method_id bigint(20) unsigned,
    ship_to_name varchar(256),
    address1 varchar(512),
    address2 varchar(512),
    city varchar(128),
    state varchar(128),
    postal_code varchar(64),
    countrqqy varchar(128),
    PRIMARY KEY(id),
    FOREIGN KEY(profile_id) references sd_profiles(id),
    FOREIGN KEY(tier_id) references sd_tiers(id),
    FOREIGN KEY(child_payment_method_id) references sd_child_payment_methods(id),
    KEY `sd_subscriptions_user_id_idx` (user_id)
) ENGINE=InnoDB;

-- Balance sheet transaction types
create table sd_transaction_types (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    title varchar(128) NOT NULL,
    PRIMARY KEY(id)
) ENGINE=InnoDB;

-- Do our inserts.
insert into sd_transaction_types (id, title) values (1, 'Fee');
insert into sd_transaction_types (id, title) values (2, 'Payment Received');
insert into sd_transaction_types (id, title) values (3, 'Withdraw');
insert into sd_transaction_types (id, title) values (4, 'Credit');
insert into sd_transaction_types (id, title) values (5, 'Payment Sent');


-- Balance sheet for profiles.
create table sd_balance_sheet (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    user_id bigint(20) unsigned NOT NULL,
    type_id bigint(20) unsigned NOT NULL,
    notes text,
    created datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    transaction decimal(10, 2),
    balance decimal(10, 2),
    order_id bigint(20) unsigned,
    subscription_id bigint(20) unsigned,
    payee_id bigint(20) unsigned,
    PRIMARY KEY(id),
    KEY sd_bs_created (created),
    FOREIGN KEY (type_id) references sd_transaction_types (id),
    FOREIGN KEY (order_id) references sd_orders (id),
    FOREIGN KEY (subscription_id) references sd_subscriptions(id),
    KEY `sd_balance_sheet_user_id_idx` (user_id)
) ENGINE=InnoDB;

