-- Add patreon linkage info to profiles
alter table sd_profiles add column patreon_client_id varchar(256);
alter table sd_profiles add column patreon_client_secret varchar(256);
alter table sd_profiles add column patreon_access_token varchar(256);
alter table sd_profiles add column patreon_refresh_token varchar(256);
alter table sd_profiles add column patreon_access_expires datetime;
alter table sd_profiles add column is_hiatus tinyint(1) not null default 0;
alter table sd_profiles add column patreon_campaign_id bigint(20);
create index sd_profiles_patreon_access_expires_idx on sd_profiles(patreon_access_expires);
create index sd_profiles_campaign_id_idx on sd_profiles(patreon_campaign_id);

-- Add info to subscriptions
alter table sd_subscriptions add column is_patreon tinyint(1) default 0;
create index sd_subscriptions_is_patreon_idx on sd_subscriptions(is_patreon);


-- Keep track of a profile's tiers
create table sd_profile_patreon_tiers (
    id bigint(20) unsigned NOT NULL,
    profile_id bigint(20) unsigned NOT NULL,
    price decimal(10,2),
    title varchar(512),
    content longtext,
    tier_id bigint(2) unsigned,
    PRIMARY KEY(id),
    FOREIGN KEY (profile_id) references sd_profiles(id),
    FOREIGN KEY (tier_id) references sd_tiers(id)
) ENGINE=InnoDB;

-- Create a mapping of patreon ID's and emails to Swaggerdile user
create table sd_patreon_users (
    id bigint(20) unsigned NOT NULL, -- Patreon ID is primary key
    user_id bigint(20) unsigned, -- May be NULL if not yet claimed
    email varchar(255) NOT NULL, -- Patreon email
    PRIMARY KEY(id),
    FOREIGN KEY (user_id) references tigerd_users(ID),
    UNIQUE KEY (email)
) ENGINE=InnoDB;

-- Let's keep track of a profile's patreons
create table sd_profile_patreons (
    id bigint(20) unsigned NOT NULL, -- Pledge ID
    profile_id bigint(20) unsigned NOT NULL,
    patreon_tier_id bigint(20) unsigned,
    patreon_user_id bigint(20) unsigned,
    subscription_id bigint(20) unsigned,
    price decimal(10, 2),
    PRIMARY KEY (id),
    FOREIGN KEY (profile_id) references sd_profiles(id),
    FOREIGN KEY (patreon_tier_id) references sd_profile_patreon_tiers(id),
    FOREIGN KEY (patreon_user_id) references sd_patreon_users(id),
    FOREIGN KEY (subscription_id) references sd_subscriptions(id)
) ENGINE=InnoDB;


create table sd_patreon_validate (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    validate_token char(36) NOT NULL,
    created datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    user_id bigint(20) unsigned NOT NULL,
    profile_id bigint(20) unsigned NOT NULL,
    PRIMARY KEY(id),
    UNIQUE KEY `sd_patreon_validate_token_idx` (validate_token),
    KEY `sd_patreon_validate_created_idx` (created),
    FOREIGN KEY (user_id) REFERENCES tigerd_users(ID),
    FOREIGN KEY (profile_id) REFERENCES sd_profiles(id)
) ENGINE=InnoDB;

