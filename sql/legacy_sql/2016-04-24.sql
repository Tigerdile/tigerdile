-- Create a table for requesting payouts.
create table sd_payout_requests (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    type_id bigint(20) unsigned NOT NULL,
    user_id bigint(20) unsigned NOT NULL,
    created datetime DEFAULT CURRENT_TIMESTAMP,
    amount decimal(10, 2) NOT NULL,
    is_paid tinyint(1) NOT NULL DEFAULT 0,
    balance_sheet_id bigint(20) unsigned NOT NULL,
    PRIMARY KEY(id),
    FOREIGN KEY(user_id) references tigerd_users(ID),
    FOREIGN KEY(balance_sheet_id) references sd_balance_sheet(id),
    KEY `sd_pr_created_idx` (created),
    KEY `sd_pr_is_paid_idx` (is_paid)
) ENGINE=InnoDB;
