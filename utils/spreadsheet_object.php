<?php
###
# Provides access to spreadsheet data without exposing the arcane PHPExcel library
###
# new Spreadsheet( $sheet )
# - Creates an instance of this object and loads the given spreadsheet file
# readBulkAssoc( $sheet )
# - Does a bulk-load from the given sheet number with the first row as key names
###

require_once( dirname(__FILE__) . "/phpexcel/Classes/PHPExcel/IOFactory.php" );
require_once( dirname(__FILE__) . "/phpexcel/Classes/PHPExcel.php" );

class MyReadFilter implements PHPExcel_Reader_IReadFilter
{
  public function readCell($column, $row, $worksheetName = '')
  {
    if( $column <= spreadsheet::COL_R )
      return true;
    return false;
  }
}

class Spreadsheet
{

  const COL_A	= 0;
  const COL_B	= 1;
  const COL_C	= 2;
  const COL_D	= 3;
  const COL_E	= 4;
  const COL_F	= 5;
  const COL_G	= 6;
  const COL_H	= 7;
  const COL_I	= 8;
  const COL_J	= 9;
  const COL_K	= 10;
  const COL_L	= 11;
  const COL_M	= 12;
  const COL_N	= 13;
  const COL_O	= 14;
  const COL_P	= 15;
  const COL_Q	= 16;
  const COL_R	= 17;
  const SHEET1	= 0;
  const SHEET2	= 1;
  const SHEET3	= 2;
  const SHEET4	= 3;
  const SHEET5	= 4;

  protected $spreadSheetObj	= ""; // the PHPExcel object holding our spreadsheet

  public $error_string	= "";

  ###
  # Class constructor
  ###
  # Args are:
  # - (string) The sheet to load
  # Returns:
  # - None
  ###
  function __construct( $sheet )
  {
    $this->setupRead( $sheet );
    if( $this->error_string )
      die($this->error_string); // instead of returning a value from a constructor
  }

  ###
  # Class destructor
  ###
  # Args are:
  # - None
  # Returns:
  # - None
  ###
  function __destruct()
  {
    $this->setupUnload();
  }

  ###
  # Sets up a spreadsheet for read, but does not extract any data from it
  ###
  # Args are:
  # - (string) The sheet to load
  # Returns:
  # - (bolean) True for success, false for failure
  # The spreadsheet object is stored in $this->spreadSheetObj
  ###
  private function setupRead( $sheetFile )
  {
    if( ! empty( $this->spreadSheetObj ) )
      return true;

    // set cache method
    $cacheMethod = PHPExcel_CachedObjectStorageFactory::cache_to_phpTemp;
    $cacheSettings = array( ' memoryCacheSize ' => '16MB');
    PHPExcel_Settings::setCacheStorageMethod( $cacheMethod, $cacheSettings );
    unset($cacheMethod);

    // set the spreadsheet reader
    $fileType = PHPExcel_IOFactory::identify( $sheetFile );
    $objReader = PHPExcel_IOFactory::createReader( $fileType );
    // and load only the data (not formatting)
    $objReader->setReadDataOnly( true );
    // load only the row/columns set by the filter (defined outside of this class)
    $filterSubset = new MyReadFilter();
    $objReader->setReadFilter( $filterSubset );
    unset($filterSubset);
    // make an attempt at loading the spreadsheet
    try
    {
      $this->spreadSheetObj = $objReader->load( $sheetFile );
    }
    catch( PHPExcel_Reader_Exception $e )
    {
      $this->error_string .= "Error loading spreadsheet file '$sheetFile'\n".$e->getMessage();
    }

    unset($objReader);

    if( $this->error_string )
      return false;
    return true;
  }

  ###
  # Unloads the current spreadsheet from memory
  ###
  # Args are:
  # - None
  # Returns:
  # - (bolean) True for success, false for failure
  ###
  function setupUnload()
  {
    if( ! empty($this->spreadSheetObj) )
    {
      $this->spreadSheetObj->disconnectWorksheets();
      unset( $this->spreadSheetObj );
    }
    return true;
  }

  ###
  # Does a bulk-load from the given sheet with the first row as key names
  ###
  # Args are:
  # - (integer) the sheet number to load
  # Returns:
  # - (array) the data on that sheet. The first row is taken as column names. output format is [ rowNumber ][ columnName ]
  ###
  function readBulkAssoc( $sheet )
  {
    $output = array();
    $colNames = array();
    $rowOffset = 1; // tracks how many empty rows to skip. is 1-based, not 0-based. 2 = skip the column-name row
    if( empty($this->spreadSheetObj) )
    {
      $this->error_string .= "Spreadsheet not loaded in spreadsheet->readBulkAssoc($sheet)\n";
      return false;
    }
    $this->spreadSheetObj->setActiveSheetIndex( intval($sheet) );
//    $maxRow = $this->spreadSheetObj->getActiveSheet()->getHighestRow();
    $maxRow = $this->spreadSheetObj->getActiveSheet()->getHighestDataRow();

    $maxCol = PHPExcel_Cell::columnIndexFromString( $this->spreadSheetObj->getActiveSheet()->getHighestColumn() );
    for( $col=0; $col<=$maxCol; $col++ )
    {
      $colNames[$col] = $this->spreadSheetObj->getActiveSheet()->getCellByColumnAndRow($col, 1)->getValue();
      if( ! isset( $colNames[$col] ) || empty( $colNames[$col] ) )
        unset( $colNames[$col] );
    }
    for( $row=$rowOffset; $row<=$maxRow; $row++ )
    {
      $thisOutputRow = $row-$rowOffset; // this row in the output array
      $emptyCount = 0; // how many empty columns on this row
      $output[ $thisOutputRow ] = array();
      for( $col=0; $col<$maxCol; $col++ )
      {
        // skip this if the column has no name
        if( ! isset( $colNames[$col] ) )
          continue;
        // write the data to $output
        $output[ $thisOutputRow ][ $colNames[$col] ] = $this->spreadSheetObj->getActiveSheet()->getCellByColumnAndRow($col, $row)->getValue();
      }
      // count how many blank entries in this row
      if( isset( $output[ $thisOutputRow ] ) )
      {
        for( $col=0; $col<$maxCol; $col++ )
        {
          if( ! isset($colNames[$col]) )
            continue;
          if( empty( $output[ $thisOutputRow ][ $colNames[$col] ] ) )
            $emptyCount++;
        }
        // if there are as many blank entries as there are columns, then remove this row and skip over it
        if( $emptyCount >= count( $output[ $thisOutputRow ] ) )
        {
          unset( $output[ $thisOutputRow ] );
          $rowOffset++;
        }
      }
      else
      {
        $rowOffset++;
      }
    }
    return $output;
  }

  ###
  # Writes a spreadsheet
  ###
  # Args are:
  # - None
  # Returns:
  # - (boolean) true for success, false for failure
  ###
  function write()
  {

//$objWriter = PHPExcel_IOFactory::createWriter($spreadSheetObj, 'Excel2007');
//$objWriter->save(str_replace('.php', '.xlsx', __FILE__));

    return true;
  }

}

?>
