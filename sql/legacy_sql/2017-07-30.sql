-- Migrate stream data from WordPress to Swaggerdile Profiles
alter table sd_profiles add column is_allow_guests tinyint(1) default 0;
alter table sd_profiles add column multistream_chat_option bigint(20) unsigned;
alter table sd_profiles add column rtmp_password varchar(128);
alter table sd_profiles add column stream_blurb text;
-- aspect ratio 1 == 16:9, 0 == 4:3
alter table sd_profiles add column aspect_ratio int;
alter table sd_profiles add column above_stream_html longtext;
alter table sd_profiles add column below_stream_html longtext;
alter table sd_profiles add column viewer_password varchar(128);
alter table sd_profiles add column donation_email varchar(128);
alter table sd_profiles add column last_paid_on datetime;
alter table sd_profiles add column stream_type_id int;
alter table sd_profiles add column is_tigerdile_friend tinyint(1);

create index sd_profiles_last_paid_date_idx on sd_profiles (last_paid_on);


-- Multi-Stream
create table sd_multistream (
    first_profile_id bigint(20) unsigned not null,
    second_profile_id bigint(20) unsigned not null,
    is_approved tinyint(1) default 0,
    PRIMARY KEY(first_profile_id, second_profile_id),
    FOREIGN KEY(first_profile_id) REFERENCES sd_profiles(id),
    FOREIGN KEY(second_profile_id) REFERENCES sd_profiles(id)
) ENGINE=InnoDB;

-- Moderator/banned user/etc. Link Table
create table sd_profile_user_types (
    id int(11) unsigned not null primary key auto_increment,
    title varchar(64)
) ENGINE=InnoDB;

insert into sd_profile_user_types   (id, title) values
                                    (1, 'Moderator'),
                                    (2, 'Banned User'),
                                    (3, 'Subscriber'),
                                    (4, 'Banned Subscriber'),
                                    (5, 'Friend');

create table sd_profile_users (
    profile_id bigint(20) unsigned NOT NULL,
    user_id bigint(20) unsigned NOT NULL,
    type_id int(11) unsigned NOT NULL,
    PRIMARY KEY(profile_id, user_id, type_id),
    FOREIGN KEY(profile_id) references sd_profiles(id),
    FOREIGN KEY(user_id) references tigerd_users(ID),
    FOREIGN KEY(type_id) references sd_profile_user_types(id)
) ENGINE=InnoDB;

