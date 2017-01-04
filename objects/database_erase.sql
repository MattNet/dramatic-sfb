# this file is intended to be used with the mysql cli in the fashion "# mysql -u root -p < this_file.sql"

#drop user sfbdrama_member@localhost;
use starfleetdramacampaign;
drop table if exists sfbdrama_conversion;
drop table if exists sfbdrama_empire;
drop table if exists sfbdrama_encounter;
drop table if exists sfbdrama_game;
drop table if exists sfbdrama_mapsector;
drop table if exists sfbdrama_orders;
drop table if exists player;
drop table if exists sfbdrama_ship;
drop table if exists sfbdrama_shipdesign;
FLUSH PRIVILEGES;

