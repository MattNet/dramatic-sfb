<?php
/*
Configuration file
*/
$AUTO_ORDER_ONE_SIDED_SCENARIOS = true;	// automatically give orders on how each one-sided encounter went
$BID_IN_DIRECTORY = dirname(__FILE__) . "/files/";	// folder to read the files that the various modules reference. Needs a trailing slash
$BID_IN_FILE_REGEX = "(\d+)input%2\$sto%1\$s.txt";	// the format of the input filename for the Bid Module(s)
$BID_OUT_FILE_FORMAT = "%3\$soutput%2\$sto%1\$s.html";	// the format of the output filename for the Bid Module(s)
$BLANK_ORDERS_NUMBER = 3;	// Number of blank orders to display on the orders-page
$DATA_OUT_FILE_FORMAT = "%3\$sdata%2\$sto%1\$s.js";	// the format of the data filename for the Bid & Encounter Module(s)
$DATA_OUT_FILE_REGEX = "%2\$sdata%1\$sto(\d+).js";	// the format of the data filename for the Bid & Encounter Module(s)
$DEFAULT_PRIVILEDGE = "Iron";
$ENCOUNTER_IN_DIRECTORY = $BID_IN_DIRECTORY;	// folder to read the files that the various modules reference. Needs a trailing slash
$ENCOUNTER_IN_FILE_REGEX = $BID_IN_FILE_REGEX;	// the format of the input filename for the Bid Module(s)
$ENCOUNTER_OUT_FILE_FORMAT = $BID_OUT_FILE_FORMAT;	// the format of the output filename for the Encounter Module(s)
$ERASE_PREVIOUS_RESOLUTION = true;	// set to true to erase any previous resolution of encounters before performing the current-turn resolution
$LOGFILE =  dirname(__FILE__) . "/logfile.txt";	// file to put the activity. file must exist before it will be written to
$MAX_ORDER_INPUTS = 127; // The maximum number of orders that may be given
$MODULE_FILE_STORE = $BID_IN_DIRECTORY;	// folder to put the files that the various modules produce. Needs a trailing slash
$RULES_LINK = "../docs/rules.html";	// the URL to get to the campaign's rules
$EMAIL_ADDRESS = "sfbdrama.mattnet.org";	// the hostname portion of the email address that the update emails originate from
$STOP_ON_MISSING_ORDERS = true;	// set to true if the turn processing should stop when someone has no orders in
$STOP_ON_UNFINISHED_SCENARIOS = true;	// set to true if the turn processing should stop when there are unresolved scenarios
$VERSION = "StarFleetDramaCampaign v1.0b";	// The software version

###
# Empires available to 'basicRace' priv level
###
$BASICRACE_EMPIRES = array(
"WYN"
);

###
# Available Encounters Modules
###
# This is a list of modules available to process the Encounter results
# (the input to the engine from the encounter-resolution phase)
# Format is "module name", "module name in english", "module name", ...
###
$MODULE_ENCOUNTERS_OUT = array(
// "encounterOutCLI",
// "encounterOutNone",
// "encounterOutHorizTable",
 "encounterOutVertTable"
);

###
# Available Encounters Modules
###
# This is a list of modules available to announce the Encounters
# (the output from the engine from the encounter-resolution phase)
# Format is "module name", "module name in english", "module name", ...
###
$MODULE_ENCOUNTERS_IN = array(
 "EncounterInFile"
);

###
# Available Ship-Bidding Modules
###
# This is a list of modules available to output the turn results
# (the output from the engine from the draw-encounters phase)
# Format is "module name", "module name in english", "module name", ...
###
$MODULE_BIDS_OUT = array(
// "bidOutCLI",
// "bidOutNone",
// "bidOutHorizTable",
 "bidOutVertTable"
);

###
# Available Ship-Bidding Modules
###
# This is a list of modules available to output the turn results
# (the input to the engine from the draw-encounters phase)
# Format is "module name", "module name in english", "module name", ...
###
$MODULE_BIDS_IN = array(
 "BidInFile"
);


?>
