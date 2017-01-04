function buildRow( rowIndex )
{
  // orderArray is an array of orders that is defined outside the function.
  // format is orderArray[index] = [ "type", "ship ID", "encounter ID", "design ID" ]

  var TURN_SECTION_EARLY = 0;
  var output = "";
  var id = "";

  // if we are disabling some orders and this is one of those orders, skip building the row
  if( gameObj.turnSection != TURN_SECTION_EARLY && rowIndex in orderArray )
    if( orderArray[rowIndex][0] == 'bid' ||
        orderArray[rowIndex][0] == 'build' ||
        orderArray[rowIndex][0] == 'convert' ||
        orderArray[rowIndex][0] == 'repair'
    )
      return output;

  output += "<tr><td>\n<select name='order"+rowIndex+"' autocomplete='off' onchange=''>\n<option value=''></option>\n";
  // Bid orders
  output += "<option value='bid'";
  if( rowIndex in orderArray && orderArray[rowIndex][0] == 'bid' )
    output += " selected";
  if( gameObj.turnSection != TURN_SECTION_EARLY )
    output += " disabled";
  output += ">Bid Ship to Scenario</option>\n";
  // Build orders
  output += "<option value='build'";
  if( rowIndex in orderArray && orderArray[rowIndex][0] == 'build' )
    output += " selected";
  if( gameObj.turnSection != TURN_SECTION_EARLY )
    output += " disabled";
  output += ">Build Ship</option>\n";
  // Conversion orders
  output += "<option value='convert'";
  if( rowIndex in orderArray && orderArray[rowIndex][0] == 'convert' )
    output += " selected";
  if( gameObj.turnSection != TURN_SECTION_EARLY )
    output += " disabled";
  output += ">Convert/Refit Ship</option>\n";
  // Cripple Ship orders
  output += "<option value='cripple'";
  if( rowIndex in orderArray && orderArray[rowIndex][0] == 'cripple' )
    output += " selected";
  output += ">Cripple Ship</option>\n";
  // Defeat in Scenario orders
  output += "<option value='defeat'";
  if( rowIndex in orderArray && orderArray[rowIndex][0] == 'defeat' )
    output += " selected";
  output += ">Defeat Defender in Scenario</option>\n";
  // Destroy Unit orders
  output += "<option value='destroy'";
  if( rowIndex in orderArray && orderArray[rowIndex][0] == 'destroy' )
    output += " selected";
  output += ">Destroy Ship</option>\n";
  // Gift Unit orders
  output += "<option value='gift'";
  if( rowIndex in orderArray && orderArray[rowIndex][0] == 'gift' )
    output += " selected";
  output += ">Gift Ship to Empire</option>\n";
  // Name ships
  output += "<option value='name'";
  if( rowIndex in orderArray && orderArray[rowIndex][0] == 'name' )
    output += " selected";
  output += ">Rename Empire or Ship</option>\n";
  // Repair Unit orders
  output += "<option value='repair'";
  if( rowIndex in orderArray && orderArray[rowIndex][0] == 'repair' )
    output += " selected";
  if( gameObj.turnSection != TURN_SECTION_EARLY )
    output += " disabled";
  output += ">Repair Ship</option>\n";
  // Victory in Scenario orders
  output += "<option value='victory'";
  if( rowIndex in orderArray && orderArray[rowIndex][0] == 'victory' )
    output += " selected";
  output += ">Defender in Scenario is Victorious</option>\n";

  // create the drop down that displays the various existing ships
  output += "</select>\n</td><td colspan=2>\n<select name='ship"+rowIndex+"' autocomplete='off'>\n<option value=''></option>\n";
  for( var i=0, len=unitList.length; i<len; i++ )
  {
    output += "<option value='"+unitList[i][0]+"'";
    if( rowIndex in orderArray && orderArray[rowIndex][1] == unitList[i][0] )
      output += " selected";
    output += ">"+unitList[i][1]+"</option>\n";
  }
  // create the drop down that displays the various encounters
  output += "</select>\n</td><td>\n<select name='scenario"+rowIndex+"' autocomplete='off'>\n<option value=''></option>\n";
  for( id in encounterList )
  {
    output += "<option value='"+id+"'";
    // check against 'gift' because we may hit an encounter number before hitting the proper empire number
    if( rowIndex in orderArray && orderArray[rowIndex][2] == id && orderArray[rowIndex][0] != 'gift' )
      output += " selected";
    output += ">#"+id + ": " + encounterList[id][0] + " (" + encounterList[id][1] + ")</option>\n";
  }
  // add to that drop down, the other empires
  for( id in empireList )
  {
    output += "<option value='"+id+"'";
    // check against 'gift' because we don't want to show an empire if the order doesn't use them
    if( rowIndex in orderArray && orderArray[rowIndex][2] == id && orderArray[rowIndex][0] == 'gift' )
      output += " selected";
    output += ">"+empireList[id] + "</option>\n";
  }
  output += "</select>\n</td><td>\n";

  // create the drop down that displays the various units available to be built
  // only if we can accept orders that affect this (e.g. build or conversion orders)
  if( gameObj.turnSection == TURN_SECTION_EARLY )
  {
    output += "<select name='design"+rowIndex+"' autocomplete='off'>\n<option value=''></option>\n";
    for( id in designList )
    {
      output += "<option value='"+id+"'";
      if( rowIndex in orderArray && orderArray[rowIndex][3] == id )
        output += " selected";
      output += ">"+designList[id][0]+" - "+designList[id][1]+" BPV</option>\n";
    }
    output += "</select>\n</td><td>\n";
  }
  else
  {
    // if we can't accept orders against the ship design list, then give a hidden input for the form
    output += "<input type='hidden' name='design"+rowIndex+"' value=''>\n</td><td>\n";
  }

  // create the text input that allows us to define an empire or unit name
  output += "<input type='text' name='text"+rowIndex+"' value=\"";
  if( rowIndex in orderArray && orderArray[rowIndex][4] != "" )
    output += orderArray[rowIndex][4];
  output += "\" onkeydown='if (event.keyCode == 13){ event.preventDefault(); return false; }'>\n";

  output += "</td></tr>\n";

  return output;

}

