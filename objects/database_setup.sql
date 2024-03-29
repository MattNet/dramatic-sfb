# this file is intended to be used with the mysql cli in the fashion "$ mysql -u root -p < this_file.sql"

create database IF NOT EXISTS starfleetdramacampaign;
#create user "sfbdrama_member"@"localhost" identified by "amarillodesignboard";
#grant DELETE, INSERT, SELECT, UPDATE on starfleetdramacampaign.* to "sfbdrama_member"@"localhost";
#create user "sfbdrama_admin"@"localhost" identified by "genfedkliromkzigortho";
#grant ALL on starfleetdramacampaign.* to "sfbdrama_admin"@"localhost";
#FLUSH PRIVILEGES;
use starfleetdramacampaign;

create table sfbdrama_empire (
dbid INT UNSIGNED NOT NULL AUTO_INCREMENT,
id INT UNSIGNED NOT NULL DEFAULT 0,
advance BOOL DEFAULT false,
ai TEXT NOT NULL DEFAULT "",
borders TEXT NOT NULL DEFAULT "",
game INT UNSIGNED NOT NULL DEFAULT 0,
income INT NOT NULL DEFAULT 0,
player INT UNSIGNED  NOT NULL DEFAULT 0,
race VARCHAR(126) NOT NULL DEFAULT "",
status VARCHAR(126) NOT NULL DEFAULT "",
storedEP INT NOT NULL DEFAULT 0,
textName VARCHAR(126) NOT NULL DEFAULT "",
turn INT NOT NULL DEFAULT 0,
PRIMARY KEY (dbid)
);

create table sfbdrama_encounter (
dbid INT UNSIGNED NOT NULL AUTO_INCREMENT,
id INT UNSIGNED NOT NULL DEFAULT 0,
game INT UNSIGNED NOT NULL DEFAULT 0,
playerA INT UNSIGNED  NOT NULL DEFAULT 0,
playerB INT UNSIGNED  NOT NULL DEFAULT 0,
scenario INT NOT NULL DEFAULT 0,
status INT NOT NULL DEFAULT 0,
turn INT NOT NULL DEFAULT 0,
PRIMARY KEY (dbid)
);

create table sfbdrama_game (
dbid INT UNSIGNED NOT NULL AUTO_INCREMENT,
id INT UNSIGNED NOT NULL DEFAULT 0,
allowConjectural BOOL DEFAULT 0,
allowPublicUnits BOOL DEFAULT 0,
borderSize TINYINT NOT NULL DEFAULT 0,
buildSpeed TINYINT NOT NULL DEFAULT 0,
campaignSpeed TINYINT NOT NULL DEFAULT 0,
currentTurn INT NOT NULL DEFAULT 0,
gameName VARCHAR(126) NOT NULL DEFAULT "",
gameStart INT UNSIGNED NOT NULL DEFAULT 0,
interestedPlayers VARCHAR(126) NOT NULL DEFAULT "",
largestSizeClass TINYINT UNSIGNED NOT NULL DEFAULT 0,
moderator INT UNSIGNED NOT NULL DEFAULT 0,
moduleEncountersIn VARCHAR(126) NOT NULL DEFAULT "",
moduleEncountersOut VARCHAR(126) NOT NULL DEFAULT "",
moduleBidsIn VARCHAR(126) NOT NULL DEFAULT "",
moduleBidsOut VARCHAR(126) NOT NULL DEFAULT "",
overwhelmingForce INT UNSIGNED NOT NULL DEFAULT 0,
randomSeeds TEXT NOT NULL DEFAULT "",
status SET ("closed", "open", "progressing") NOT NULL DEFAULT "",
turnSection TINYINT NOT NULL DEFAULT 0,
useExperience BOOL DEFAULT 0,
useUnitSwapping BOOL DEFAULT 0,
PRIMARY KEY (dbid)
);

create table sfbdrama_orders (
dbid INT UNSIGNED NOT NULL AUTO_INCREMENT,
id INT UNSIGNED NOT NULL DEFAULT 0,
game INT UNSIGNED NOT NULL DEFAULT 0,
orders TEXT NOT NULL DEFAULT "",
empire INT UNSIGNED  NOT NULL DEFAULT 0,
turn SMALLINT UNSIGNED NOT NULL DEFAULT 0,
PRIMARY KEY (dbid)
);

create table player (
dbid INT UNSIGNED NOT NULL AUTO_INCREMENT,
id INT UNSIGNED NOT NULL DEFAULT 0,
configuration VARCHAR(126) NOT NULL DEFAULT "",
email VARCHAR(126) NOT NULL DEFAULT "",
fullName VARCHAR(126) NOT NULL DEFAULT "",
isApproved BOOL NOT NULL DEFAULT FALSE,
isVerified BOOL NOT NULL DEFAULT FALSE,
pass VARCHAR(126) NOT NULL DEFAULT "",
priviledges VARCHAR(30) NOT NULL DEFAULT "",
sessionID VARCHAR(126) NOT NULL DEFAULT "",
sessionTime INT UNSIGNED NOT NULL DEFAULT 0,
signupDate TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
theme VARCHAR(30) NOT NULL DEFAULT "",
username VARCHAR(126) NOT NULL DEFAULT "",
verifyKey VARCHAR(126) NOT NULL DEFAULT "",
PRIMARY KEY (dbid)
);

create table sfbdrama_ship (
dbid INT UNSIGNED NOT NULL AUTO_INCREMENT,
id INT UNSIGNED NOT NULL DEFAULT 0,
captureEmpire INT UNSIGNED  NOT NULL DEFAULT 0,
configuration VARCHAR(126) NOT NULL DEFAULT "",
damage TINYINT NOT NULL DEFAULT 0,
design INT NOT NULL DEFAULT 0,
empire INT UNSIGNED  NOT NULL DEFAULT 0,
game INT UNSIGNED NOT NULL DEFAULT 0,
isDead BOOL DEFAULT 0,
locationIsLane BOOL NOT NULL DEFAULT 0,
manifest INT NOT NULL DEFAULT 0,
mapSector INT UNSIGNED  NOT NULL DEFAULT 0,
mapObject INT UNSIGNED  NOT NULL DEFAULT 0,
status ENUM ("Active", "Reserve", "Mothball") NOT NULL DEFAULT "Active",
supplyLevel INT NOT NULL DEFAULT 0,
textName VARCHAR(126) NOT NULL DEFAULT "",
turn SMALLINT UNSIGNED NOT NULL DEFAULT 0,
PRIMARY KEY (dbid)
);

create table sfbdrama_shipdesign (
dbid INT UNSIGNED NOT NULL AUTO_INCREMENT,
id INT UNSIGNED NOT NULL DEFAULT 0,
BPV SMALLINT UNSIGNED NOT NULL DEFAULT 0,
baseHull VARCHAR(126) NOT NULL DEFAULT "",
carrier TINYINT UNSIGNED NOT NULL DEFAULT 0,
carrierHeavy TINYINT UNSIGNED NOT NULL DEFAULT 0,
carrierPFT TINYINT UNSIGNED NOT NULL DEFAULT 0,
carrierBomber TINYINT UNSIGNED NOT NULL DEFAULT 0,
carrierHvyBomber TINYINT UNSIGNED NOT NULL DEFAULT 0,
commandRating TINYINT UNSIGNED NOT NULL DEFAULT 0,
configA TINYINT UNSIGNED NOT NULL DEFAULT 0,
configB TINYINT UNSIGNED NOT NULL DEFAULT 0,
configLTT TINYINT UNSIGNED NOT NULL DEFAULT 0,
configTug TINYINT UNSIGNED NOT NULL DEFAULT 0,
designator VARCHAR(126) NOT NULL DEFAULT "",
empire VARCHAR(126) NOT NULL DEFAULT "",
fighterMechLink TINYINT UNSIGNED NOT NULL DEFAULT 0,
heavyMechLink TINYINT UNSIGNED NOT NULL DEFAULT 0,
obsolete TINYINT UNSIGNED NOT NULL DEFAULT 0,
opt TINYINT UNSIGNED NOT NULL DEFAULT 0,
opt2 TINYINT UNSIGNED NOT NULL DEFAULT 0,
shipyard TINYINT UNSIGNED NOT NULL DEFAULT 0,
sidcorAtk TINYINT UNSIGNED NOT NULL DEFAULT 0,
sidcorDmg TINYINT UNSIGNED NOT NULL DEFAULT 0,
sidcorCAtk TINYINT UNSIGNED NOT NULL DEFAULT 0,
sidcorCDmg TINYINT UNSIGNED NOT NULL DEFAULT 0,
sidcorEW TINYINT UNSIGNED NOT NULL DEFAULT 0,
sizeClass TINYINT UNSIGNED NOT NULL DEFAULT 0,
supplyCarry TINYINT UNSIGNED NOT NULL DEFAULT 0,
troopCarry TINYINT UNSIGNED NOT NULL DEFAULT 0,
yearInService SMALLINT UNSIGNED NOT NULL DEFAULT 0,
switches SET ("bombardment", "civilian", "cloak", "commandPost", "conjectural", "escort", "fast", "mineLayer", "scout", "slow", "supplyDepot", "tug","mauler") NOT NULL DEFAULT "",
PRIMARY KEY (dbid)
);

