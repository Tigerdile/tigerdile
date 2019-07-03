-- SQL updates for implementing stream re-broadcasting.

create table sd_rebroadcasts (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    profile_id bigint(20) unsigned NOT NULL,
    title varchar(128) NOT NULL,
    target_url text NOT NULL,
    stream_key text,
    is_enabled tinyint,
    PRIMARY KEY(id),
    FOREIGN KEY (profile_id) REFERENCES sd_profiles(id)
) ENGINE=InnoDB;


