drop table if exists "account";

create table account
(
	"id"  integer primary key autoincrement,
	"account_name"  varchar(50),
	"short_name"  varchar(30),
	"merchant_id"  varchar(50) not null,
	"market_place_id"  varchar(50) not null,
	"aws_access_key_id"  varchar(100) not null,
	"secret_key"  varchar(100) not null, 
	"service_url"  varchar(255) not null,
	"status"  smallint,
	"site"  varchar(32),
	"sort"  integer
);