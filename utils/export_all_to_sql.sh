#!/bin/sh

# Exports the ship design table from the database into a .sql file

OUTPUT_FILE="/home/www/sfbdrama.21.sql";

mysqldump --add-drop-database --add-drop-table --databases -uroot -ppne65536 starfleetdramacampaign >$OUTPUT_FILE
