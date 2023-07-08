
/*
Builds an order entry as hidden inputs and then submits the form

Inputs:
- (obj) The element that was clicked on
- (int) value to use in the menu-names to indicate which order this is for
- (int) index value into unitList to pull out the ship ID
- (int) index value into encounterList to pull out the encounter ID
*/
function submitImage( element, orderIndex, shipIndex, encounterIndex )
{
  // point to the element holding our source tag
  element = element.parentElement;

  // append a hidden input tag: name='order"+index+"' value='bid'
  var newNode = document.createElement("input");
  newNode.name="order"+orderIndex;
  newNode.type="hidden";
  newNode.value="bid";

  element.appendChild(newNode);

  var newNode = document.createElement("input");
  newNode.name="ship"+orderIndex;
  newNode.type="hidden";
  newNode.value=shipIndex;

  element.appendChild(newNode);

  var newNode = document.createElement("input");
  newNode.name="scenario"+orderIndex;
  newNode.type="hidden";
  newNode.value=encounterIndex;

  element.appendChild(newNode);

  // search for a parent with the 'submit' method
  while( typeof element.submit != 'function')
    element = element.parentElement;

  // submit the form
  element.submit();
};

/*
Builds a row of drop-down menus

Inputs:
- (int) value to use in the menu-names to indicate which order this is for
- (int) index value into orderArray to pull out so as to pre-populate the menus
Output:

*/
function buildRow( rowIndex, listIndex )
{
  // orderArray is an array of orders that is defined outside the function.
  // format is orderArray[index] = [ "type", "ship ID", "encounter ID", "design ID" ]

//  var TURN_SECTION_EARLY = 0;
  var output = "";
  var id = "";

  // turn listIndex into an index into orderArray[] that skips certain orders
  if( listIndex in orderArray )
    if( gameObj.turnSection != TURN_SECTION_EARLY )
    {
    // if we are disabling some orders and this is one of those orders, skip building the row
        for( var i=0; i < Object.keys(orderArray).length; i++)
          if( i <= listIndex && (
              orderArray[ Object.keys(orderArray)[i] ][0] == 'build' ||
              orderArray[ Object.keys(orderArray)[i] ][0] == 'bid' ||
              orderArray[ Object.keys(orderArray)[i] ][0] == 'convert' ||
              orderArray[ Object.keys(orderArray)[i] ][0] == 'repair'
            ) )
            listIndex++;
    }
    else // this is done in the early half of the turn
    {
      if( TURN_ON_DROP_DOWN_BIDS < 1 ) // if we turn off drop-down bid interface, don't show bid orders in drop-downs
        for( var i=0; i < Object.keys(orderArray).length; i++)
          if( i <= listIndex &&
              orderArray[ Object.keys(orderArray)[i] ][0] == 'bid'
            )
            listIndex++;
    }

  output += "<tr><td>\n<select name='order"+rowIndex+"' autocomplete='off' onchange=''>\n<option value=''></option>\n";

// drop-down bid orders
// graphical bid orders are written with shipRow()
  // Bid orders
  if( TURN_ON_DROP_DOWN_BIDS > 0 ) // if we turn on drop-down bid interface, show bid orders in drop-downs
  {
    output += "<option value='bid'";
    if( listIndex in orderArray && orderArray[listIndex][0] == 'bid' )
      output += " selected";
    if( gameObj.turnSection != TURN_SECTION_EARLY )
      output += " disabled";
    output += ">Bid Ship to Scenario</option>\n";
  }
// end drop-down bid orders

  // Build orders
  output += "<option value='build'";
  if( gameObj.turnSection == TURN_SECTION_EARLY && listIndex in orderArray && orderArray[listIndex][0] == 'build' )
    output += " selected";
  if( gameObj.turnSection != TURN_SECTION_EARLY )
    output += " disabled";
  output += ">Build Ship</option>\n";
  // Conversion orders
  output += "<option value='convert'";
  if( listIndex in orderArray && orderArray[listIndex][0] == 'convert' )
    output += " selected";
  if( gameObj.turnSection != TURN_SECTION_EARLY )
    output += " disabled";
  output += ">Convert/Refit Ship</option>\n";
  // Cripple Ship orders
  output += "<option value='cripple'";
  if( listIndex in orderArray && orderArray[listIndex][0] == 'cripple' )
    output += " selected";
  output += ">Cripple Ship</option>\n";
  // Defeat in Scenario orders
  output += "<option value='defeat'";
  if( listIndex in orderArray && orderArray[listIndex][0] == 'defeat' )
    output += " selected";
  output += ">Defeat Defender in Scenario</option>\n";
  // Destroy Unit orders
  output += "<option value='destroy'";
  if( listIndex in orderArray && orderArray[listIndex][0] == 'destroy' )
    output += " selected";
  output += ">Destroy Ship</option>\n";
  // Gift Unit orders
  output += "<option value='gift'";
  if( listIndex in orderArray && orderArray[listIndex][0] == 'gift' )
    output += " selected";
  output += ">Gift Ship to Empire</option>\n";
  // Name ships
  output += "<option value='name'";
  if( listIndex in orderArray && orderArray[listIndex][0] == 'name' )
    output += " selected";
  output += ">Rename Empire or Ship</option>\n";
  // Repair Unit orders
  output += "<option value='repair'";
  if( listIndex in orderArray && orderArray[listIndex][0] == 'repair' )
    output += " selected";
  if( gameObj.turnSection != TURN_SECTION_EARLY )
    output += " disabled";
  output += ">Repair Ship</option>\n";
  // Victory in Scenario orders
  output += "<option value='victory'";
  if( listIndex in orderArray && orderArray[listIndex][0] == 'victory' )
    output += " selected";
  output += ">Defender in Scenario is Victorious</option>\n";

  // create the drop down that displays the various existing ships
  output += "</select>\n</td><td colspan=2>\n<select name='ship"+rowIndex+"' autocomplete='off'>\n<option value=''></option>\n";
  for( var i=0; i<Object.keys(unitList).length; i++ )
  {
    var key = Object.keys(unitList)[i]
    output += "<option value='"+key+"'";
    if( listIndex in orderArray && orderArray[listIndex][1] == key )
      output += " selected";
    output += ">"+unitList[key][1]+"</option>\n";
  }
  // create the drop down that displays the various encounters
  output += "</select>\n</td><td>\n<select name='scenario"+rowIndex+"' autocomplete='off'>\n<option value=''></option>\n";
  for( id in encounterList )
  {
    output += "<option value='"+id+"'";
    // check against 'gift' because we may hit an encounter number before hitting the proper empire number
    if( listIndex in orderArray && orderArray[listIndex][2] == id && orderArray[listIndex][0] != 'gift' )
      output += " selected";
    output += ">#"+id + ": " + encounterList[id][0] + " (" + encounterList[id][1] + ")</option>\n";
  }
  // add to that drop down, the other empires
  for( id in empireList )
  {
    output += "<option value='"+id+"'";
    // check against 'gift' because we don't want to show an empire if the order doesn't use them
    if( listIndex in orderArray && orderArray[listIndex][2] == id && orderArray[listIndex][0] == 'gift' )
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
      if( listIndex in orderArray && orderArray[listIndex][3] == id )
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
  if( listIndex in orderArray && orderArray[listIndex][4] != "" )
    output += orderArray[listIndex][4];
  output += "\" onkeydown='if (event.keyCode == 13){ event.preventDefault(); return false; }'>\n";

  output += "</td></tr>\n";

  return output;

}
/*
Builds a row (with indexes into unitList[]) that creates a bid order for a ship

Input:
- (int) value to use in the menu-names to indicate which order this is for
- (int) index value into unitList to pull out the ship
Output:
- (string) The HTML for the row in question
*/
function shipRow( rowIndex, listIndex )
{
  // orderArray is an array of orders that is defined outside the function.
  // format is orderArray[index] = [ "type", "ship ID", "encounter ID", "design ID" ]

  var TURN_SECTION_EARLY = 0;
  var output = "<tr><td>";
  var shipGraphic = "/campaign/images/"+unitList[listIndex][3].toLowerCase()+unitList[listIndex][2].toLowerCase()+".svg";
  var ourEncounterID = -1;

  // Skip building this if we can't give bid orders right now
  if( gameObj.turnSection != TURN_SECTION_EARLY )
    return output;

  // figure out which encounter this ship was bid to
  for( var orderIndex=0; orderIndex < Object.keys(orderArray).length; orderIndex++)
    if( orderArray[orderIndex][0] == 'bid' && orderArray[orderIndex][1] == unitList[listIndex][0] )
      ourEncounterID = orderArray[orderIndex][2];

// Put aura around image to denote when it is a previously-given order
  output += "<a href='#' title='"+unitList[listIndex][1]+"'><img src='"+shipGraphic;
  output += "' alt='"+String(unitList[listIndex][1]).toHtmlEntities()+"' class='scenario' style='float:left'></a>"+unitList[listIndex][1];
  output += "\n</td><td class='scenario_table_units' colspan=4>\n";

  for( var i=0; i<Object.keys(encounterList).length; i++)
  {
    var listKey = Object.keys(encounterList)[i];
    // If we have an order for this ship, propogate the order with the next submit
    // else, kill the order entirely
    if( ourEncounterID > -1 && ourEncounterID == listKey )
    {
      output += "<input type='hidden' name='order"+rowIndex+"' value='bid'>";
      output += "<input type='hidden' name='ship"+rowIndex+"' value='"+unitList[listIndex][0]+"'>";
      output += "<input type='hidden' name='scenario"+rowIndex+"' value='"+listKey+"'>\n";
    }

    // do the regular scenario list
    output += "<a href='#' title='"+encounterList[listKey][0]+" #"+listKey+"' onclick='submitImage(this,";
    output += rowIndex+","+unitList[listIndex][0]+","+listKey+")'><img src='../scenarios/";
    output += encounterList[listKey][2]+"' alt='"+String(encounterList[listKey][0]).toHtmlEntities()+" #"+listKey+"' class='scenario";
    if( ourEncounterID > -1 && ourEncounterID == listKey )
      output += " bid_orders"
    output += "'></a>\n";
  }

  output += "</td></tr>\n";

  return output;

}

/*
Convert a string to HTML entities

From http://stackoverflow.com/questions/18749591/encode-html-entities-in-javascript
By ar34z
*/
String.prototype.toHtmlEntities = function() {
    return this.replace(/./gm, function(s) {
        return "&#" + s.charCodeAt(0) + ";";
    });
};

