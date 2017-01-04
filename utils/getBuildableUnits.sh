
EMPIRE="Seltorian";
YEAR="172";
OUTPUT_FILE=`pwd`"/output.csv";

HEADERS="empire,designator,BPV,sizeClass,commandRating,yearInService,obsolete,baseHull,carrier";
QUOTED_HEADERS="'empire','designator','BPV','sizeClass','commandRating','yearInService','obsolete','baseHull','carrier'";
NO_OUTFILE="SELECT ${QUOTED_HEADERS} UNION SELECT ${HEADERS} FROM sfbdrama_shipdesign WHERE empire='${EMPIRE}' AND yearInService<='${YEAR}' AND (obsolete>='${YEAR}' OR obsolete=0)";
SQL_LINE="${NO_OUTFILE} INTO OUTFILE '${OUTPUT_FILE}' FIELDS TERMINATED BY ',' ENCLOSED BY '\"' LINES TERMINATED BY '\n'";


echo $NO_OUTFILE;

