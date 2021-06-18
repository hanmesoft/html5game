<?
/*
  $Id$
 (c) 2010 Dmitry Yu. Kazarov mailto:kazarov@users.sourceforge.net
 Based upon sudoku generator from project ksudoku
  http://ksudoku.sf.net
  (c) 2005 Francesco Rossi <redsh@email.it>
  (c) 2007 Johannes Bergmeier <Johannes.Bergmeier@gmx.net>
	   Mick Kappenburg <ksudoku@kappenburg.net>
	   Francesco Rossi <redsh@email.it>
*/

ini_set('xdebug.max_nesting_level',1000);

define('SUDOKUBASE',3);
define('SUDOKUORDER',SUDOKUBASE*SUDOKUBASE);
define('SUDOKDATAFILE',$_SERVER['SCRIPT_FILENAME'].'.data');

setDefines();

if( !isset($_SERVER['SERVER_NAME']) ) {
  CLI_functions();
  exit(0);
}

ini_set('max_execution_time',180);

switch( isset($_REQUEST['Komanda'])?$_REQUEST['Komanda']:'HTML' ) {
default:
case 'HTML':
  otdatiHTML();
  exit(0);
case 'CIFRA':
  otdatiCifru();
  exit(0);
case 'KREST':
  otdatiKrest();
  exit(0);
case 'INFO':
  phpinfo();
  exit(0);
}

// Misc Functions
function fetchSudoku($level,$symmetry) {
  if( $symmetry == 0 ) {
    $symvals = array();
    global $simNames;
    foreach( $simNames as $symmetry => $sName ) 
      if( $symmetry != 0 ) $symvals[] = $symmetry;
    $symmetry = $symvals[rand(0,count($symvals)-1)];
  }
  if( !file_exists(SUDOKDATAFILE) ) return null;
  $FHdlr = fopen(SUDOKDATAFILE,"r");
  if( !$FHdlr ) return null;
  $sudokus = array();
  while( $line = fgets($FHdlr) ) {
    if( substr($line,0,1) == '#' ) continue;
    if(
      preg_match('/^((\d+)\s+(\d+)\s+(\d+)\s+(\S+)\s+(\S+))\s+([0-9a-fA-F]+)\s*$/',$line,$match) &&
      crc32($match[1]) == hexdec($match[7]) &&
      intval($match[2]) == SUDOKUBASE &&
      intval($match[3]) == $level &&
      intval($match[4]) == $symmetry
    ) {
      $sudokus[] = array( 'puzzle'=>$match[5], 'solutn'=>$match[6] );
    }
  }
  fclose($FHdlr);

  if( count($sudokus) < 1 ) return null;

  $game = $sudokus[ rand(0,count($sudokus)-1) ];

  $puzzle = new SKPuzzle(SUDOKUORDER,sudoku);
  $solutn = new SKPuzzle(SUDOKUORDER,sudoku);

  for($cntr=0;$cntr<$puzzle->size;$cntr++) {
    if( $cntr < strlen($game['puzzle']) ) {
      $chr = ord(substr($game['puzzle'],$cntr,1)) - $puzzle->zerochar;
      $puzzle->numbers[$cntr] = $chr>=0 ? $chr : 0;
    }
    if( $cntr < strlen($game['solutn']) ) {
      $chr = ord(substr($game['solutn'],$cntr,1)) - $puzzle->zerochar;
      $solutn->numbers[$cntr] = $chr>=0 ? $chr : 0;
    }
  }

  return array( 'puzzle'=>$puzzle, 'solution'=>$solutn );
}

function CLI_functions() {
  $opts = getopt('hcf:n:');

  if( !isset($opts['c']) || isset($opts['h']) ) {
    print <<< Usage
Судоку от Дмитрия.
Автор: Дмитрий Юрьевич Казаров (kazarov@users.sourceforge.net)
Генератор, решатель, игра Судоку. При запуске с командной строки создаёт файл с головоломками, при размещении на
вебсервере создаёт страницу с игрой, позволяющей решать головоломку с комфортом.
Т.к. создание головоломок занимает очень много времени (20-300 секунд на головоломку), желательно заранее создать
файл с головоломками выполнив команду {$_SERVER['PHP_SELF']} -c и переместив полученный файл в каталог к вебскрипту.

Параметры командной строки
{$_SERVER['PHP_SELF']} [-c [ -f имяфайла ]] | [ -h ]
	-c	Создание файла. Если не указан параметр -f то создаётся файл с полным именем
		исполняемого файла и дополнительным расширением .data ({SUDOKDATAFILE}.data).
	-f	Имя создаваемого файла.
	-h	Эта справка.
	-n	Общее количество создаваемых головоломок (изначально 8192).

Usage;
    exit(0);
  }
  $pzlNum = isset($opts['n']) && intval($opts['n'])? intval($opts['n']) : 8192;
  $fName  = isset($opts['f']) && $opts['f'] ? $opts['f']: SUDOKDATAFILE;

  $FHdlr = fopen($fName,"w");
  if( !$FHdlr ) {
    print  "Не удалось создать файл {$fName}\n";
    exit(0);
  }
  global $difNames, $simNames;
  $simCnt = 0;
  foreach( $simNames as $symmetry => $sName ) if( $symmetry != 0 ) $simCnt++;
  $puzlsPerSym = intval($pzlNum/($simCnt*count($difNames)));
  $actPzlsNum = $puzlsPerSym * $simCnt*count($difNames);
  print "Будет создано {$actPzlsNum} головоломок (по $puzlsPerSym на каждый вариант Сложность+Симметрия)\n";
  for($level=0,$created=0;$level<count($difNames);$level++) {
    print "Сложность: {$difNames[$level]}.\n";
    fwrite($FHdlr, "# Сложность {$difNames[$level]}\n");
    foreach( $simNames as $symmetry => $sName ) {
      if( $symmetry == 0 ) continue;
      print "Сложность: {$difNames[$level]}; Симметрия: {$sName}.\n";
      fwrite($FHdlr, "## Сложность: {$difNames[$level]}; Симметрия: {$sName}.\n");
      for($cntr=0;$cntr<$puzlsPerSym;$cntr++,$created++) {
	print 'Выполнено '.intval(($created*100)/$actPzlsNum)."% ({$created} из {$actPzlsNum})\r";
	$sudoku = generateSudoku($level,$symmetry);
	$zerochar = $sudoku['puzzle']->zerochar;
	$puzzle = '';
	foreach( $sudoku['puzzle']->numbers   as $num ) $puzzle   .= chr($num+$zerochar);
	$solution = '';
	foreach( $sudoku['solution']->numbers as $num ) $solution .= chr($num+$zerochar);
	$outstr = "{$sudoku['puzzle']->base} {$level} {$symmetry} {$puzzle} {$solution}";
	$crc = dechex(crc32($outstr));
	fwrite($FHdlr, "{$outstr} {$crc}\n");
      }
      print
	'Выполнено '.intval(($created*100)/$actPzlsNum)."% ({$created} из {$actPzlsNum})\n".
	"Сложность \"{$difNames[$level]}\", Симметрия \"{$sName}\" закончена\n";
    }
    print "\nСложность \"{$difNames[$level]}\" закончена\n";
  }
  fclose($FHdlr);
}

// HTML Production
function chooseLang() {
  if( isset($_SERVER["HTTP_ACCEPT_LANGUAGE"]) ) { // ru-ru,ru;q=0.8,en-us;q=0.5,en;q=0.3
    foreach( explode(',', $_SERVER["HTTP_ACCEPT_LANGUAGE"]) as $lang )
      switch( strtolower ($lang) ) {
      case 'ru':
      case 'ru-ru':
	return 'Rus';
      case 'en':
      case 'en-us':
	return 'Eng';
      }
  }
  return 'Eng';
}

function otdatiHTML() {
  $ja = $_SERVER['PHP_SELF'];
  $level     = isset($_REQUEST['level'])?intval($_REQUEST['level']):1;
  $symmetry  = isset($_REQUEST['symmetry'])?intval($_REQUEST['symmetry']):0;
  $highlight = isset($_REQUEST['highlight'])?intval($_REQUEST['highlight']):0;
  $language  = isset($_REQUEST['language'])?$_REQUEST['language']:chooseLang();


/*
  ini_set('xdebug.collect_assignments',1);
  ini_set('xdebug.collect_params',1);
  xdebug_start_trace();
*/
  $sudoku = function_exists('fetchSudoku')?fetchSudoku($level,$symmetry):null;
  
//  xdebug_stop_trace();

  header('Content-type: text/html; charset=UTF-8');
  header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
  header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" 
  "http://www.w3.org/TR/html4/loose.dtd">
<html>
  <head>
    <title>Судоку от Дмитрия</title>
    <style type="text/css">

    #PuzzleFld {
      background-color: white;
    }

    #PuzzleFld TD {
      font-size: 	56px;
      font-weight:	bold;
      text-align:	center;
      vertical-align:	middle;
      border-style:	solid;
      border-color:	black;
      background-position: 50% 50%;
      width:		64px;
      height:		64px;
    }
    #PuzzleFld TD IMG {
      width:		64px;
      height:		64px;
    }
    #PuzzleFld TD TABLE.microTBL {
      border-style:	none;
      width:		100%;
      height:		100%;
    }
    #PuzzleFld TD TABLE.microTBL TD {
      border-style:	none;
      font-size: 	14px;
      font-weight:	normal;
      color:		lightgrey;
      cursor:		pointer;
      width:		33%;
      height:		33%;
    }

    #PuzzleFld TD TABLE.microTBL TD:hover {
      background-color: lightgrey;
      border-style:	none;
      font-size: 	14px;
      font-weight:     normal;
      color:           black;
      cursor:          pointer;
      width:           33%;
      height:          33%;
    }

    #PuzzleFld TD TABLE.microTBL TD.otm {
      border-style:    none;
      font-size:       14px;
      font-weight:     700;
      color:           black;
      cursor:          pointer;
      width:           33%;
      height:          33%;
    }

    #PuzzleFld TD TABLE.microTBL TD.otm:hover {
      background-color: lightgrey;
      border-style:    none;
      font-size:       14px;
      font-weight:	900;
      color:		black;
      cursor:		pointer;
      width:		33%;
      height:		33%;
    }
    DIV.menuDiv_off {
      position: absolute;
      top:	0;
      right:	10px;
    }
    DIV.menuDiv {
      text-align:	center;
    }
    DIV.Header0 {
      text-align:	center;
      font-size:	x-large;
      font-weight:	bold;
    }
    DIV.Header1 {
      text-align:	center;
      font-size:	large;
      font-weight:	bold;
    }
    TD.newGameOpts {
      border-right: 4px dotted black;
    }
    TD.curGameOpts {
    }

    .SFLogo {
      position: fixed;
      bottom:	5px;
      left:	5px;
    }
    .SFLogo IMG {
      border: none;
    }

    #donateHandler {
      position: fixed;
      top:	4em;
      left:	5px;
      text-decoration: none;
      color:	#0000EE;
      background:	Beige;
      border:		1px solid #F2AF1D;
    }

    #donateHandler:hover {
      position: fixed;
      top:	4em;
      left:	5px;
      text-decoration: underline;
      color:	#0000EE;
      background:	Beige;
      border:		1px solid #F2AF1D;
    }

    #donateHandler span {
      display:	block;
      width:	6em;
    }

    #donateHandler .donations_innerSpan {
      display:	none;
    }

    #donateHandler:hover .donations_innerSpan {
      display:	block;
      width:	25em;
    }

    .helpHandler {
      position:	absolute;
      top:	2em;
      left:	5px;
      text-decoration: none;
      color:	#0000EE;
    }
    .HelpBody {
      display:	none;
    }
    .helpHandler:hover .HelpBody {
      background:	Beige;
      border:		1px solid #F2AF1D;
      display:		block;
      position:		absolute;
      top:		1em;
      width:		700px;
      z-index:		1;
      padding:		8px;
      text-align:	justify;
    }
    .HelpBody SPAN {
      font-weight:	bold;
      color:		DarkCyan;
    }

    .LangChooser {
      position: absolute;
      top:      2em;
      right:	5px;
      text-decoration: none;
      color:	#0000EE;
    }

    .LangBody {
      display:	none;
    }

    .LangChooser:hover .LangBody {
      display:	block;
    }

    .backup {
      background: wheat;
      background: #FDF1D5;
    }
    </style>
    <!-- color: BlueViolet; // Brown, Chocolate Coral DarkBlue DarkCyan -->
    <script type="text/javascript">
      //<![CDATA[
      var LangStrData = {
	Rus: {
	  label:	'Rus',
	  headTitle:	'Судоку от Дмитрия',
	  congratMsg: "Поздравляем! Судоку успешно решена!\nХотите сыграть ещё раз?",
	  longCrtMsg: "<?=function_exists('fetchSudoku')?'Не удалось загрузить заготовленную головоломку. ':''?>Подождите - идёт генерация, это может занять несколько минут.",
	  elementsID: {
	    fldBigTitle:	'Игра Судоку',

            complexLabel:	'Сложность',
	    complexValue_0:	'Легко',
	    complexValue_1:	'Средне',
	    complexValue_2:	'Сложно',
	    complexValue_3:	'Очень сложно',
	    complx2Label:	'Сложность',

	    symmetryLabel:	'Симметрия',
	    symmetry2Label:	'Симметрия',
	    symmetryValue_0:	'Случайная',
	    symmetryValue_1:	'Без симметрии',
	    symmetryValue_2:	'Диагональная',
	    symmetryValue_3:	'Центральная',
	    symmetryValue_4:	'Четырёхсторонняя',

	    HighlightLbl:	'Подсвечивать возможные ячейки',

	    btn_clrAll:		'Очистить',
	    btn_probeAll:	'Пометить все варианты',
	    btn_clrPrAll:	'Очистить все варианты',
	    savingsLegend:	'Состояние поля',
	    btn_saveCurState:	'Запомнить',
	    btn_restCurState:	'Восстановить',

	    pleaseDonateHndlr:	'Если Вам понравилось',
	    pleaseDonateBody:	'рассмотрите возможность сделать пожертвование на разработку этой и других игр. Мы сделаем ваше время приятнее!'
	  },
	  inputsID: {
	    submitInput:	'Новая игра'
	  }
	},
	Eng: {
	  label:	'Eng',
	  headTitle:	"Dmitry's Sudoku",
	  congratMsg: "Congratulations! This is a correct solution.\nDo you want to play again?",
	  longCrtMsg: "<?=function_exists('fetchSudoku')?'No precalculated puzzle found. ':''?>Please wait, calculating new puzzle. It can take some minutes.",
	  elementsID: {
	    fldBigTitle:	'Sudoku Puzzle',

            complexLabel:	'Difficulty',
	    complexValue_0:	'Easy',
	    complexValue_1:	'Moderate',
	    complexValue_2:	'Hard',
	    complexValue_3:	'Extra hard',
	    complx2Label:	'Difficulty',

	    symmetryLabel:	'Symmetry',
	    symmetry2Label:	'Symmetry',
	    symmetryValue_0:	'Random',
	    symmetryValue_1:	'None',
	    symmetryValue_2:	'Diagonal',
	    symmetryValue_3:	'Central',
	    symmetryValue_4:	'Four way',

	    HighlightLbl:	'Highlight possible cells',

	    btn_clrAll:		'Clear',
	    btn_probeAll:	'Mark all options',
	    btn_clrPrAll:	'Clear all options',
	    savingsLegend:	'Board\'s states',
	    btn_saveCurState:	'Save',
	    btn_restCurState:	'Restore',

	    pleaseDonateHndlr:	'If you like it',
	    pleaseDonateBody:	'please consider to make a donation. We will spend it to bring you more pleasure!'
	  },
	  inputsID: {
	    submitInput:	'New game'
	  }
	}
      };
      var CurLang = LangStrData.<?=$language?>;
      //]]>
    </script>
  </head>
  <body>
    <table style="display:none">
<?
  for($sc=1;$sc<=SUDOKUORDER;$sc++) {
    print "<tr>\n";
    for($sv=0;$sv<SUDOKUORDER;$sv++)
      print "<td><img id=\"cifra_{$sc}_{$sv}\" src=\"{$ja}?Komanda=CIFRA&amp;Cifra={$sc}&amp;Variant={$sv}\" alt=\"{$sc}\"/></td>\n";
    print "</tr>\n";
  }
?>
      <tr><td><img id="krest" src="<?=$ja?>?Komanda=KREST" alt="WRONG!" /></td></tr>
    </table>

    <script type="text/javascript">
      //<![CDATA[
// SelectAddOption
      function SelectAddOption(sel,name,value,slctd) {
	var rv      = document.createElement("option");
	rv.text     = name;
	rv.value    = value;
	rv.selected = slctd;
	if( sel.options && sel.options.add )
	  sel.options.add(rv);
	else
	  sel.add(rv,null);
	sel.selectedIndex = sel.options.length - 1;
	return rv;
      }

      if (typeof document.body.onselectstart!="undefined") // IE
	document.body.onselectstart=function(){return false}
      else if(typeof document.body.style.MozUserSelect!="undefined") //Firefox
	document.body.style.MozUserSelect="none"


      var Highlight = <?=$highlight?>;
      var Kartinki = new Array();
      for(var sc=1;sc<=<?=SUDOKUORDER?>;sc++) {
	var tKart = new Array();
	for(var sv=0;sv< <?=SUDOKUORDER?>;sv++) tKart.push( document.getElementById("cifra_"+sc+"_"+sv) );
	Kartinki[sc] = tKart;
	// Kartinki[sc] = new Array();
	// for(sv=0;sv< <?=SUDOKUORDER?>;sv++) // Перемешиваем массив цифр - дабы от игры к игре выглядело
	//				    // несколько живее.
	//  Kartinki[sc].push(tKart.splice( Math.floor(Math.random() * tKart.length), 1)[0]);
      }
      Kartinki.krest = document.getElementById('krest');

// fixHighlight
      function fixHighlight() {
	for(var cell=0;cell<Cells.length;cell++)
	  Cells[cell].clrHighlight();
      }

// setHighlight
      function setHighlight(no) {
	if( !Highlight ) return;
	for(var cell=0;cell<Cells.length;cell++)
	  Cells[cell].setHighlight(no);
      }

// clrHighlight
      function clrHighlight() {
	if( !Highlight ) return;
	fixHighlight();
      }

// chgHighlight
      function chgHighlight(chkbox) {
	Highlight= chkbox.checked?1:0;
	fixHighlight();
      }

// showObject
      function showObject(name,o) {
	str = '';
	if( o ) {
	  str = name+':'+o+';\n';
	  for( var key in o ) {
	    str += name+'.'+key+' = '+o[key]+';\n';
	  }
	  for(var key = 0; key < o.length; key++) {
	    str += name+'['+key+'] = '+o[key]+';\n';
	  }
	} else {
	  str = name + ' is null.';
	}
	window.alert(str);
      }

// stopEvent
      function stopEvent(event) {
	if( event.preventDefault ) event.preventDefault();
	event.returnValue = false;
	if( event.stopPropagation ) event.stopPropagation();
	event.cancelBubble = true;
      }

// createProbeTable
      function createProbeTable(cell) {
	var tbl		= document.createElement("TABLE");
	tbl.className	= 'microTBL';
	tbl.cellSpacing	= 0;
	tbl.align	= 'center';
	var sudokuValues = new Array();
	for( var r = 0, s=1; r < <?=SUDOKUBASE?>; r++ ) {
	  // var tr  = document.createElement("TR");
	  var tr  = tbl.insertRow(-1);
	  for( var c = 0; c < <?=SUDOKUBASE?>; c++, s++ ) {
	    // var tc  = document.createElement("TD");
	    var tc  = tr.insertCell(-1);
	    //tc.innerHTML = s;
	    //tc.innerHTML = document.createTextNode(s);
	    tc.appendChild(document.createTextNode(s));
	    tc.sudokuValue = s;
	    tc.sudokuCell = cell;
	    sudokuValues[s] = tc;
	    // tr.appendChild(tc);

	    tc.ondblclick = function(event) {
	      // stopEvent(event);
	      this.sudokuCell.setVal(this.sudokuValue);
	      return false;
	    };
	    tc.onclick = function(event) {
	      this.className = this.className ? '':'otm';
	      this.sudokuCell.setHighlight(this.sudokuValue);
	    }
	    tc.isProbeSet = function() {
		return this.className != '';
	    }
	    tc.onmouseover = function() { setHighlight( this.sudokuValue ); }
	    tc.onmouseout  = function() { clrHighlight( ); }
	  }
	  // tbl.appendChild(tr);
	}
	tbl.sudokuValues = sudokuValues;
	return tbl;
      }

// checkDone
      function checkDone() {
	for( var cn=0; cn < Cells.length; cn++ )
	  if( Cells[cn].no != Cells[cn].cor ) return;
	var frm = document.getElementById('newGameForm');
	if( confirm( CurLang.congratMsg ) )
	  frm.submit();
      }

// SudokuCell object
      function SudokuCell(no,sol,td_id,idx) {
	this.idx = idx;
	this.row = Math.floor(idx/<?=SUDOKUORDER?>);
	this.col = idx % <?=SUDOKUORDER?>;
        this.blk =
	  Math.floor( Math.floor(idx/<?=SUDOKUORDER?>)/<?=SUDOKUBASE?> )*<?=SUDOKUBASE?> +
	  Math.floor( (idx%<?=SUDOKUORDER?>)/<?=SUDOKUBASE?> ); 
	this.no = no;
	this.cor = sol;
	this.preset = (no != 0);
	this.conflict = 0;
	this.hightlighted = 0;
	this.tableCell = document.getElementById(td_id);
	this.groups = new Array();
	if( !this.preset ) {
	  this.probeTable = createProbeTable(this);
	  var tc = this.tableCell;
	  while( tc.childNodes.length > 0 ) tc.removeChild(tc.firstChild);
	  tc.appendChild(this.probeTable);
	} else {
	  // this.tableCell.ondblclick = this.tableCell.onclick = function(event) { stopEvent(event); return true; }
	  this.tableCell.sudokuValue = no;
	  this.tableCell.onmouseover = function() { setHighlight( this.sudokuValue ); }
	  this.tableCell.onmouseout  = function() { clrHighlight( ); }
	}
      }

// clrVal
      SudokuCell.prototype.clrVal = function() {
	if( this.preset || this.no == 0 ) return;
	var conflictsList = this.chkConflict();
	this.doClrConflict();
	this.tableCell.replaceChild(this.probeTable,this.tableCell.firstChild);
	this.no = 0;
	for(var c=0; c < conflictsList.length; c++ )
	  conflictsList[c].clrConflict();
      }

// setVal
      SudokuCell.prototype.setVal = function(no) { 
	if( this.preset || this.no == no ) return;
	this.clrVal();
	this.no = no;
	if( this.no == 0 ) return;

	// var kartinka = Kartinki[no][this.blk].cloneNode(true);
	//  Kartinki[sc].push(tKart.splice( Math.floor(Math.random() * tKart.length), 1)[0]);
	var kartinka = Kartinki[no][ Math.floor(Math.random() * Kartinki[no].length) ].cloneNode(true);
	kartinka.sudokuCell = this;
	kartinka.ondblclick = function() {
	  this.sudokuCell.clrVal();
	};
	kartinka.onmouseover = function() { setHighlight( this.sudokuCell.no ); }
	kartinka.onmouseout  = function() { clrHighlight( ); }
	this.tableCell.replaceChild(kartinka,this.tableCell.firstChild);
	var conflictsList = this.chkConflict();
	if( conflictsList.length ) {
	  this.setConflict();
	  for( var cell=0; cell< conflictsList.length; cell++ )
	    conflictsList[cell].setConflict();
	}
	this.fixProbes();
	fixHighlight();
	setHighlight(this.no);
	checkDone();
      }

// chkConflict
      SudokuCell.prototype.chkConflict = function() {
	var conflictList = new Array();
	//      debugger;
	for( var grp = 0; grp < this.groups.length; grp++ ) {
	  var group = this.groups[grp];
	  for( var gI = 0; gI < group.length; gI++ )
	    if( group[gI] != this && group[gI].no == this.no )
	      conflictList.push(group[gI]);
	}
	return conflictList;
      }

// doClrConflict
      SudokuCell.prototype.doClrConflict = function() {
	this.conflict = 0;
	this.tableCell.style.backgroundImage = '';
      }

// setConflict
      SudokuCell.prototype.setConflict = function() {
	this.conflict = 1;
	this.tableCell.style.backgroundImage = 'url('+Kartinki.krest.src+')';
	/* debugger;
	* var k = Kartinki.krest.cloneNode(true);
	* k.width=64;
	* k = k.src;
	* this.tableCell.style.backgroundImage = 'url('+k+')'; */
      }

// clrConflict
      SudokuCell.prototype.clrConflict = function() {
	var conflictsList = this.chkConflict();
	if( conflictsList.length != 0 ) return;
	this.doClrConflict();
      }

// canHaveValue
      SudokuCell.prototype.canHaveValue = function(no) {
	for( var grp = 0; grp < this.groups.length; grp++ ) {
	  var group = this.groups[grp];
	  for( var gI = 0; gI < group.length; gI++ )
	    if( group[gI].no == no ) return false;
	}
	return true;
      }

// setHighlight
      SudokuCell.prototype.setHighlight = function(no) {
	if( this.preset || this.no != 0 ) return;
	this.clrHighlight();

	if( !this.canHaveValue(no) ) return;

	this.tableCell.style.backgroundColor = this.isProbeSet(no) ?  'lightBlue' : 'rgb(214, 236, 245)';
	this.hightlighted = 1;
      }

// isProbeSet
      SudokuCell.prototype.isProbeSet = function(no) {
	return this.probeTable.sudokuValues[no].isProbeSet();
      }

// clrHighlight
      SudokuCell.prototype.clrHighlight = function() {
	if( this.preset || !this.hightlighted ) return;
	this.tableCell.style.backgroundColor = '';
	this.hightlighted = 0;
      }

// fixProbes
      SudokuCell.prototype.fixProbes = function() {
	for(var grp =0; grp<this.groups.length; grp++) {
	  for(var idx = 0; idx<this.groups[grp].length; idx++) {
	    var cell = this.groups[grp][idx];
	    if( cell == this || cell.preset ) continue;
	    cell.probeTable.sudokuValues[this.no].className = '';
	    // cell.probeTable.sudokuValues[this.no].style.color = '';
	  }
	}
      }

// setAllProbes
      function setAllProbes() {
	for(var cell=0;cell<Cells.length;cell++)
	  if( Cells[cell].probeTable )
	    for( var num=1; num <= <?=SUDOKUORDER?>; num++ ) 
	      Cells[cell].probeTable.sudokuValues[num].className = Cells[cell].canHaveValue(num)? 'otm' : '';
	      // Cells[cell].probeTable.sudokuValues[num].style.color = Cells[cell].canHaveValue(num)? 'black' : '';
      }

// clrAllProbes
      function clrAllProbes() {
	for(var cell=0;cell<Cells.length;cell++)
	  if( Cells[cell].probeTable )
	    for( var num=1; num <= <?=SUDOKUORDER?>; num++ ) 
	      Cells[cell].probeTable.sudokuValues[num].className = '';
	      // Cells[cell].probeTable.sudokuValues[num].style.color = '';
      }

// clearAll
      function clearAll() {
	for(var cell=0;cell<Cells.length;cell++) {
	  Cells[cell].clrVal();
	  for( var num=1; num <= <?=SUDOKUORDER?>; num++ ) 
	    if( Cells[cell].probeTable )
	      Cells[cell].probeTable.sudokuValues[num].className = '';
	      // Cells[cell].probeTable.sudokuValues[num].style.color = '';
	}
      }

// saveState
      function saveState() {
	var curSt = new Array();
	for( var cell=0; cell<Cells.length; cell++ ) {
	  var cellState = new Object();
	  cellState.number = Cells[cell].no
	  cellState.probes = new Array();
	  if( Cells[cell].probeTable ) {
	    var probes = Cells[cell].probeTable;
	    for( var num = 1; num <= <?=SUDOKUORDER?>; num++ )
	      if( probes.sudokuValues[num].className != '' )
		cellState.probes.push(num);
	      // if( probes.sudokuValues[num].style.color != '' )
	  }
	  curSt.push(cellState);
	}
	return curSt;
      }

// restState
      function restState(state) {
	clearAll();
	for( var cell=0; cell<Cells.length; cell++ ) {
	  cellState = state[cell];
	  Cells[cell].setVal(cellState.number);
	  if( Cells[cell].probeTable ) {
	    var probes = Cells[cell].probeTable;
	    for( var num = 0; num < cellState.probes.length; num++ )
	      probes.sudokuValues[ cellState.probes[num] ].className = 'otm';
	      // probes.sudokuValues[ cellState.probes[num] ].style.color = 'black';
	  }
	}
	fixHighlight();
      }

      function checkTextInput(event) {
	if( event.keyCode == 13 ) {
	  var btn = document.getElementById('btn_saveCurState');
	  btn.click();
	  return false;
	}
	return true;
      }
      function callSaveState() {
	var curState = saveState();

	var nameSrc  = document.getElementById('saveNameInput');
	if( nameSrc.value != '' )
	  curState.name = nameSrc.value;
	else
	  curState.name = (new Date()).toLocaleString();

	var saverSel = document.getElementById('savesList');
	for(var cnt=0; cnt<saverSel.options.length; cnt++)
	  if( saverSel.options[cnt].text == curState.name ) {
	    curState.name += ' '+saverSel.options.length;
	    break;
	  }
	var savedOpt = SelectAddOption(saverSel,curState.name,saverSel.options.length,1);
	savedOpt.sudokuState = curState;

	nameSrc.value = '';
	return false;
      }

      function callRestState() {
	var saverSel = document.getElementById('savesList');
	for(var cntr=0;cntr<saverSel.options.length;cntr++)
	  if( saverSel.options[cntr].selected && saverSel.options[cntr].sudokuState ) {
	    restState(saverSel.options[cntr].sudokuState);
	    break;
	  }
	return false;
      }

      function fixLanguage() {
	var elt = document.getElementById('language');
	if( elt ) elt.value = CurLang.label;

	document.title = CurLang.headTitle;

	for( var id in CurLang.elementsID ) {
	  if( elt = document.getElementById(id)          ) elt.innerHTML = CurLang.elementsID[id];
	  if( elt = document.getElementById('extra_'+id) ) elt.innerHTML = CurLang.elementsID[id];
	}
	for( var id in CurLang.inputsID   ) {
	  if( elt = document.getElementById(id)          ) elt.value = CurLang.inputsID[id];
	}
	for( var id in LangStrData ) {
	  elt = document.getElementById('help'+id);
	  elt.style.display = id==CurLang.label?'':'none';
	}
      }

      function changeLang(elt) {
	if( !elt.checked ) return;
	if( elt.value in LangStrData && elt.value != CurLang.label ) {
	  CurLang = LangStrData[elt.value];
	  fixLanguage();
	}
      }
      //]]>
    </script>
<?
  if( !$sudoku ) {
?>
    <script type="text/javascript">
      //<![CDATA[
      // document.body.removeChild(document.getElementById('warningLoading'));
      var longCreationText = document.createTextNode(CurLang.longCrtMsg);
      document.body.appendChild(longCreationText);
      //]]>
    </script>
    <!-- div id="warningLoading">
    </div -->
<!--
<?
  ob_flush();
  flush();

  $sudoku = generateSudoku($level,$symmetry);
?>
-->
    <script type="text/javascript">
      //<![CDATA[
      // document.body.removeChild(document.getElementById('warningLoading'));
      document.body.removeChild(longCreationText);
      //]]>
    </script>
<?
  }
  global $simNames, $difNames;
  // <span id="complexValue_{$level}">{$difNames[$level]}</span>.
  //<span id="symmetryLabel">Симметрия</span>: <span id="symmetryValeu_{$symmetry}">{$simNames[$symmetry]}</span>.</div>
  print <<<HeaderLine
<div class="Header0"><span id="fldBigTitle">Игра Судоку</span></div>
<div class="Header1">
  <span id="complexLabel">Сложность</span>: <span id="complexValue_{$level}">{$difNames[$level]}</span>.
  <span id="symmetryLabel">Симметрия</span>: <span id="symmetryValue_{$symmetry}">{$simNames[$symmetry]}</span>.</div>
HeaderLine;

  prtSudoku($sudoku['puzzle'],'PuzzleFld');

  $playedCntr = isset($_REQUEST['playedCntr'])?(intval($_REQUEST['playedCntr'])+1):0;
?>
  <div id="menu" class="menuDiv">
    <table align="center"><tr>
      <td class="newGameOpts">
	<form id="newGameForm" Action="" Method="get">
	  <input type="hidden" id="playedCntr" name="playedCntr" value="<?=$playedCntr?>" />
	  <input type="hidden" id="language"   name="language"   value="<?=$language?>"   />
	  <table>
	    <tr>
	      <td colspan="2" >
		<INPUT TYPE=checkbox id="highlight" name="highlight" <?=$highlight?'checked="true"':''?>
		  onclick="chgHighlight(this)"
		  value="1"
		/>
		<span id="HighlightLbl">Подсвечивать возможные варианты</span>
	      </td>
	    </tr>
	    <tr>
	      <td id="complx2Label">Сложность</td>
	      <td><select id="level" name="level">
	      <?
		global $difNames;
		foreach( $difNames as $key => $val ) {
		  $selected = $level==$key?'selected="selected"':'';
		  print "<option value=\"{$key}\" {$selected} id=\"extra_complexValue_{$key}\" >{$val}</option>\n";
		}
	      ?>
	      </select></td>
	    </tr>
	    <tr>
	      <td id="symmetry2Label">Симметрия</td>
	      <td><select id="symmetry" name="symmetry">
	      <?
		global $simNames;
		foreach( $simNames as $key => $val ) {
		  $selected = $symmetry==$key?'selected="selected"':'';
		  print "<option value=\"{$key}\" {$selected} id=\"extra_symmetryValue_{$key}\">{$val}</option>\n";
		}
	      ?>
	      </select></td>
	    </tr>
	    <tr><td colspan="2" align="center"><input id="submitInput" type="submit" value="Новая игра" /><td></tr>
	  </table>
	</form>
      </td>
      <td class="curGameOpts">
	<button id="btn_clrAll"   onclick="clearAll();     return false;">Очистить</button><br />
	<button id="btn_probeAll" onclick="setAllProbes(); return false;">Отметить все варианты</button><br />
	<button id="btn_clrPrAll" onclick="clrAllProbes(); return false;">Очистить все варианты</button><br />
	<FIELDSET><LEGEND id="savingsLegend">Состояния поля</LEGEND>
	  <select id="savesList" size="2" ><option value="0">0</option></select><br />
	  <input type="text" id="saveNameInput" size="20" maxlength="20" onkeypress="return checkTextInput(event)" />
	    <br />
	  <button id="btn_saveCurState" onclick="return callSaveState();" >Запомнить</button>
	  <button id="btn_restCurState" onclick="return callRestState();" >Восстановить</button>
	</FIELDSET>
      </td>
    </tr></table>
  </div>
  <!-- href="javascript:void(null)" -->
  <div id="helpRus" class="helpHandler">
    <span id="RusHelpHandlerText">Справка</span>
    <div class="HelpBody">
      <p><span>Игра Судоку</span> - головоломка с простыми правилами но требующая серьёзных размышлений,
      хорошая тренировка для ума и прожорливый пожиратель времени. В современном виде она родилась в США в
      1979 году в журнале Dell Puzzle Magazine. Но популярность ей принес Японский журнал Nikoli в 1986 году,
      который и дал ей современное название.</p>
      <p><span>Цель игры</span> - заполнить поле цифрами от 1 до 9 так, чтобы цифры не повторялись ни в строке,
      ни в столбце, ни в малом квадрате (3х3 клеток, выделенном более толстой рамкой).</p>
      <p><span>Автор этой реализации</span>: Дмитрий Юрьевич Казаров
      <a href="mailto:kazarov@users.sourceforge.net?subject=Websudoku">написать письмо</a></p>
      <p><span>Как играть</span>: поле состоит из ячеек. В начале, в некоторых ячейках находятся числа, отрисованные
      типографским шрифтом. Эти числа не возможно менять и они задают конечное решение головоломки (<b>Значение
      ячейки</b>). В остальных ячейках находятся маленькие светлосерые цифры от 1 до 9 - это <b>варианты</b>
      значений данной ячейки. 
      <b>Однократный</b> шелчёк левой кнопкой мыши на варианте выделяет его чёрным цветом среди остальных вариантов.
      Это помогает запомнить какую цифру Вы хотели поставить в данную ячейку. Так можно выделить несколько вариантов.
      <b>Двойной</b> щелчёк левой кнопкой мыши на варианте выбирает его в качестве <b>решения</b> для этой
      ячейки.
      <b>Двойной</b> щелчёк на ячейке с решением убирает это решение и возвращает список вариантов.
      </p>
      <p><span>Под игровым полем</span>:<br />
	Переключатель <b>"Подсвечивать возможные варианты"</b> включает подсветку ячеек в которые можно поставить
	число, над которым находится курсор мыши (<b>значения ячейки</b> или один из <b>вариантов</b>), без
	конфликтов с другими, уже установленными числами. Этот параметр также передаётся и в новую игру.<br />
	Выборы <b>"Сложность"</b> и <b>"Симметрия"</b> позволяют задать параметры для новой игры.<br />
	Кнопка <b>"Новая игра"</b> загружает новую игру с выбранными параметрами <b>"Сложность"</b> и
	<b>"Симметрия"</b>.<br />
	<br />
	Кнопка <b>"Очистить"</b> приводит головоломку в исходное состояние - удаляет все <b>значения ячейки</b> и
	<b>варианты</b>.<br />
	Кнопки <b>"Отметить все варианты"</b> и <b>"Очистить все варианты"</b> соответсвенно выделяют и снимают 
	выделение всех возможных вариантов во всех ячейках головоломки.<br />
	<br />
	<b>Текущее состояние головоломки</b> можно сохранить с помощью кнопки <b>"Запомнить"</b>. Состоянию можно
	дать имя с помощью текстового поля над кнопкой иначе оно получит имя по текущему местному времени.
	Запомненные состояния отображаются в списке над текстовым полем.<br />
	Потом это состояние можно вернуть выбрав нужное из списка и нажав кнопку <b>"Восстановить"</b>
      </p>
    </div>
  </div>
  <!-- href="javascript:void(null);" -->
  <div id="helpEng" style="display:none" class="helpHandler">
    <span id="EngHelpHandlerText">Help</span>
    <div class="HelpBody">
      <p><span>Sudoku</span> - is a logic-based, number-placement puzzle. The modern Sudoku was most likely designed
      anonymously by Howard Garns, a 74-year-old retired architect and freelance puzzle constructor from Indiana,
      and first published in 1979 by Dell Magazines as Number Place (the earliest known examples of modern Sudoku).
      The puzzle was popularized in 1986 by the Japanese puzzle company Nikoli, under the name Sudoku,
      meaning single number. It became an international hit in 2005. (from Wikipedia,
      http://en.wikipedia.org/wiki/Sudoku)</p>
      <p><span>The objective</span> is to fill a 9×9 grid with digits so that each column, each row,
      and each of the nine 3×3 sub-grids that compose the grid (also called "boxes", "blocks", "regions",
      or "sub-squares") contains all of the digits from 1 to 9.
      The same single integer may not appear twice (a) in the same 9x9 playing board row,
      (b) in the same 9x9 playing board column or (c) in any of the nine 3x3 subregions of the 9x9 playing board.</p>
      <p><span>Author</span> Dmitry Y. Kazarov
      <a href="mailto:kazarov@users.sourceforge.net?subject=WebSudoku">write an E-mail</a>.</p>
      <p><span>How to play</span>: Playing board consists of cells. Some cells contains the unchangeable black
      digits.  Other cells contains light grey numbers from 1 to 9. These are options for this cell.
      <b>Single click</b> on an option hightlights it with black color. It helps to memorize probable
      digit for this cell.
      <b>Double click</b> on an option to make it a solution for this cell (blue colored digit).
      <b>Double click</b> on a cell with solution removes solution and restores list of options.
      </p>
      <p><span>Under playing board</span>:<br />
      Checkbox <b>Highlight possible cells</b> controls highlighting of cells which can contain digits pointed 
      by mouse cursor (<b>cell values</b> or <b>options</b>) without conflicting with other cell values.<br />
      Selectors <b>Difficulty</b> and <b>Symmetry</b> sets these parameters for new game.<br />
      Button <b>New game</b> loads new puzzle (all saved states becomes unavailable).<br />
      Button <b>Clear</b> removes all changes to puzzle restoring initial state of the game.<br />
      Button <b>Mark all options</b> highlights all options not conflicting with the cell's values<br />
      Button <b>Clear all options</b> removes highlightings from all options.<br />
      Using buttons <b>Save</b> and <b>Restore</b> you can store and recall state of playing board for current
      game. The reloading of the game removes all saved states. Text field above buttons allows to give a name to
      state.</p>
    </div>
  </div>
  <div class="LangChooser">
    Language/Язык
    <span class="LangBody" >
      <input type="radio" name="languageSelector" value="Eng" onclick="changeLang(this);return true;"
	<?=$language=='Eng'?'checked="checked"':''?> />English<br />
      <input type="radio" name="languageSelector" value="Rus" onclick="changeLang(this);return true;"
	<?=$language=='Rus'?'checked="checked"':''?> />Русский
    </span>
  </div>
<? /* if( $playedCntr > 3 && rand(1,8)>4 ) { ? >
  <a href="http://sourceforge.net/donate/index.php?group_id=362437" target="_blank" id="donateHandler">
    <span id="pleaseDonateHndlr">Если Вам понравилось</span>
    <span id="pleaseDonateBody" class="donations_innerSpan">рассмотрите возможность сделать пожертвование на
    разработку этой и других игр. Мы сделаем ваше время приятнее!</span>
    <img src="http://images.sourceforge.net/images/project-support.jpg" width="88" height="32"
      border="0" alt="Support This Project" /> 
  </a>
< ? } */ ?>
  <div class="SFLogo"><a href="http://sourceforge.net/projects/websudoku"><img
	src="http://sflogo.sourceforge.net/sflogo.php?group_id=362437&amp;type=10"
	alt="Websudoku at SourceForge.net"
   ></a></div>
  <script type="text/javascript">
    //<![CDATA[
// debugger;
    var Cells = new Array(
<?
  for($s=0;$s<SUDOKUORDER*SUDOKUORDER;$s++) {
    if( $s > 0 ) print ",\n";
    print "   new SudokuCell({$sudoku['puzzle']->numbers[$s]},{$sudoku['solution']->numbers[$s]},'cell_{$s}',{$s})";
  }
  print "\n";
?>
    );
    for( var r=0, s=0; r< <?=SUDOKUORDER?>; r++ ) {
      var grp = new Array();
      for( var c=0; c< <?=SUDOKUORDER?>; c++, s++ ) {
	var cell = Cells[s];
	grp.push(cell);
	cell.groups[0] = grp;
      }
    }
    for( var c=0; c< <?=SUDOKUORDER?>; c++ ) {
      var grp = new Array();
      for( var r=0; r< <?=SUDOKUORDER?>; r++ ) {
	var s;
	var cell = Cells[s=r*<?=SUDOKUORDER?> + c];
	grp.push(cell);
	cell.groups[1] = grp;
      }
    }
    var blks = new Array(<?=SUDOKUORDER?>);
    for( var blk = 0; blk < <?=SUDOKUORDER?>; blk++ )
      blks[blk] = new Array();
    for( var c = 0; c < <?=SUDOKUORDER*SUDOKUORDER?>; c++ ) {
      var cell = Cells[c];
      var blk  = cell.blk;
      blks[blk].push( cell );
      cell.groups[2] = blks[blk];
    }

    fixLanguage();
    var sL = document.getElementById('savesList');
    while( sL.childNodes.length )
      sL.removeChild(sL.firstChild)
    //]]>
  </script>
  </body>
</html>
<?
}

function borderWidth($r,$c,$base=3) {
  $last = $base*$base-1;
  $leftBrdr = ($c % $base == 0);
  $lastColm  = ($c == $last);

  if( $r%$base == 0 ) {
    if( $leftBrdr ) { return '3px 0 0 3px';   }
    if( $lastColm ) { return '3px 3px 0 1px'; }
    return '3px 0 0 1px';
  }
  if( $r == $last ) {
    if( $leftBrdr ) { return '1px 0 3px 3px';   }
    if( $lastColm ) { return '1px 3px 3px 1px'; }
    return '1px 0 3px 1px';
  }
  if( $leftBrdr ) { return '1px 0 0 3px';   }
  if( $lastColm ) { return '1px 3px 0 1px'; }
  return '1px 0 0 1px';
}

function prtSudoku($puzzle,$tableID) {
  $defStyle='border-width:';
  print "\n <table id='{$tableID}' cellspacing='0' cellpadding='0' align='center'>\n";
  for($r=0,$s=0;$r<SUDOKUORDER;$r++) {
    print '<tr>';
    for($c=0;$c<SUDOKUORDER;$c++,$s++ ) {
      $bw=borderWidth($r,$c);
      $nu=$puzzle->numbers[$s]>0?chr($puzzle->numbers[$s]+$puzzle->zerochar):'&nbsp;';
      print "<td width='64' id='cell_{$s}' height='64' style='{$defStyle}{$bw}'>{$nu}</td>";
    }
    print "</tr>\n";
  }
  print "</table>\n";
}

//require_once('FirePHPCore/FirePHP.class.php');
//ob_start();

/*
Copied from
Part of KSudoku project
http://ksudoku.sf.net
(c) 2005 Francesco Rossi <redsh@email.it>
(c) 2007 Johannes Bergmeier <Johannes.Bergmeier@gmx.net>
         Mick Kappenburg <ksudoku@kappenburg.net>
	 Francesco Rossi <redsh@email.it>
*/

$CurDebug = 0;
function debugStack() {
  global $CurDebug;
  if( !$CurDebug ) return;
  print "<pre>\n=========== ============\n";
  foreach( xdebug_get_function_stack() as $no => $st ) {
    $p = implode(', ',$st['params']);
    if( !isset($st['class']) ) $st['class'] = '';
    if( !isset($st['line']) )  $st['line']  = '';
    print "{$no}: {$st['class']}::{$st['function']}({$p}) @ {$st['line']}\n";
  }
  print "</pre>\n";
}

function setDefines() {

define(  "sudoku", 0);
define( "roxdoku", 1);
define(  "custom", 2);

//enum ProcessState
define( 'KSS_SUCCESS', 0); /// An operation finished successfuly
define( 'KSS_FAILURE', 1); /// An operation failed, this may (dependant on op) mean that there is no solution
define( 'KSS_ENOUGH_SOLUTIONS', 2); /// An operation stopped, as no more solutions are required
define( 'KSS_ENOUGH_FORKS', 3); /// To count of maximum forks was exceeded
define( 'KSS_CRITICAL', 4); /// There is an internal failure

//enum DifficultyFlags 
define( 'KSS_SYM_NONE',     0);
define( 'KSS_SYM_DIAGONAL', 1);
define( 'KSS_SYM_CENTRAL',  2);
define( 'KSS_SYM_FOURWAY',  KSS_SYM_DIAGONAL | KSS_SYM_CENTRAL);
define( 'KSS_SYM_MASK',     KSS_SYM_FOURWAY);
define( 'KSS_REM_1VALUE',   4);


define('MAX_FORKS',15000);

define( 'LEVINC',0);

define( 'SIMMETRY_RANDOM',   0);
define( 'SIMMETRY_NONE',     1);
define( 'SIMMETRY_DIAGONAL', 2);
define( 'SIMMETRY_CENTRAL',  3);
define( 'SIMMETRY_FOURWAY',  4);

define( 'NAME_SIMMETRY_RANDOM',   'Случайная');
define( 'NAME_SIMMETRY_NONE',     'Без симметрии');
define( 'NAME_SIMMETRY_DIAGONAL', 'Диагональная');
define( 'NAME_SIMMETRY_CENTRAL',  'Центральная');
define( 'NAME_SIMMETRY_FOURWAY',  'Четырёхсторонняя');

global $simNames, $difNames;
$simNames = array(
  SIMMETRY_RANDOM   => NAME_SIMMETRY_RANDOM,
  SIMMETRY_NONE     => NAME_SIMMETRY_NONE,
  SIMMETRY_DIAGONAL => NAME_SIMMETRY_DIAGONAL,
  SIMMETRY_CENTRAL  => NAME_SIMMETRY_CENTRAL,
  SIMMETRY_FOURWAY  => NAME_SIMMETRY_FOURWAY,
);
$difNames = array( 'Легко', 'Средне', 'Сложно', 'Сверхсложно' );
}

class Puzzle {
  var $m_withSolution = 0;
  var $m_puzzle     = NULL;
  var $m_solution   = NULL;
  var $m_solver     = NULL;

  var $m_difficulty = 0;
  var $m_symmetry   = 0;

  function __construct($solver,$withSolution=1) {
    $this->m_withSolution = $withSolution;
    $this->m_solver       = $solver;
  }

  function __destruct() {
    $this->m_puzzle   = NULL;
    $this->m_solution = NULL;
  }

  function init() {
    switch( func_num_args() ) {
    case 0: initNoContent(); break;
    case 2: break;
    default:
      echo "Wrong number or arguments in call to Puzzle::{$name}\n";
      return false;
    }
    $arg0 = func_get_arg(0);
    $arg1 = func_get_arg(1);
    if( is_array($arg0) && is_array($arg1) )
      $this->initValSol($arg0,$arg1);
    elseif( is_array($arg0) ) 
      $this->initValForks($arg0,$arg1);
    else
      $this->initDiffSymm($arg0,$arg1);
  }

  function initNoContent() { // no args
    if( isset($this->m_puzzle) ) return False;
    if( $this->m_withSolution )  return False;

    $this->m_puzzle = new SKPuzzle($this->m_solver->g->order, $this->m_solver->g->type);
    $this->m_puzzle->numbers = array_fill(0,$this->m_puzzle->size,0);
#    for($i=0;$i<$this->m_puzzle->size;$i++)
#      $this->m_puzzle->numbers[$i] = 0;
    return True;
  }

  function initDiffSymm($difficulty, $symmetry) { // int, int
    if( isset($this->m_puzzle) ) return False;
    if( $difficulty >= 0 || $difficulty <=3 ) { $difficulty = 2 -$difficulty; }
    else                                      { $difficulty = 2 -1;           }


    $puzzle = new SKPuzzle($this->m_solver->g->order, $this->m_solver->g->type, $this->m_solver->g->size);

    if( !isset($puzzle) ) return False;

#    $puzzle->randomize();

    $this->m_solver->solve($puzzle, 1, $puzzle);

    $solution = NULL;
    if( $this->m_withSolution ) {
      $solution = new SKPuzzle($this->m_solver->g->order, $this->m_solver->g->type);
      if( !isset($solution) ) return False;
      $this->m_solver->copy($solution, $puzzle);
    }

    $this->m_solver->remove_numbers($puzzle, $difficulty, $symmetry, $this->m_solver->g->type);

    $this->m_difficulty = $difficulty;
    $this->m_symmetry   = $symmetry;
    $this->m_puzzle     = $puzzle;
    $this->m_solution   = $solution;
    return true;
  }

  function initValForks($values,$forks) { // Array, int
    if( isset($this->m_puzzle) ) return -1;

    # debugPrint_r('solver',$this->m_solver);
        
    $puzzle   = new SKPuzzle($this->m_solver->g->order, $this->m_solver->type());
    $solution = new SKPuzzle($this->m_solver->g->order, $this->m_solver->type());
        
    if(!isset($puzzle, $solution)) return -1;
        
    for($i = 0; $i < $this->m_solver->g->size; ++$i)
       $puzzle->numbers[$i] = ord($values[$i]) - $puzzle->zerochar;

    $success = $this->m_solver->solve($puzzle, 1, $solution, $forks);
    if($success == 0) return 0;
        
    $success = $this->m_solver->solve($puzzle, 2);
        
    $this->m_puzzle = $puzzle;
    if($this->m_withSolution) $this->m_solution = $solution;
    return $success;
  }

  function initValSol($values, $solutionValues) { // Array, Array
    if( isset($this->m_puzzle) ) return False;
        
    $puzzle = new SKPuzzle($this->m_solver->g->order, $this->m_solver->type());
    if(!isset($puzzle)) return false;
        
#    for($i = 0; $i < $this->m_solver->g->size; ++$i)
#      $puzzle->numbers[$i] = $values[$i];
    $puzzle->numbers = array_merge($values);
        
    if(count($solutionValues) != 0) {
      $solution = new SKPuzzle($this->m_solver->g->order, $this->m_solver->type());
      if(!isset($solution)) return false;
                
#      for($i = 0; $i < $this->m_solver->g->size; ++$i)
#        $solution->numbers[$i] = $solutionValues[$i];
                
      $solution->numbers = array_merge($solutionValues);
      $this->m_solution = $solution;
    }
    $this->m_puzzle = $puzzle;
    return True;
  }

  function gameType() {
    return ($this->m_solver->g->type==0) ? sudoku : ($this->m_solver->g->type==1 ? roxdoku : custom);
  }
  function valueLinear($index) { return $this->m_puzzle ? $this->m_puzzle->numbers[$index] : 0; }
  function valueXYZ($x, $y, $z = 0) { return $this->valueLinear($this->index($x,$y,$z)); }
  function solution($index) { return $this->m_solution ? $this->m_solution->numbers[$index] : 0; }
  function hasSolution() { return $this->m_withSolution && $this->m_solution; }
  function index($x, $y, $z = 0) {
    if(!$this->m_solver) return 0;
    return ($x*$this->m_solver->g->sizeY() + $y)*$this->m_solver->g->sizeZ() + $z;
  }
  function order() { return $this->m_solver->g->order; }
  function size() { return $this->m_solver->g->sizeX() * $this->m_solver->g->sizeY() * $this->m_solver->g->sizeZ(); }
  function optimized_d($index) { return $this->m_solver->g->optimized_d[$index]; }
  function optimized($indX, $indY) { return $this->m_solver->g->optimized[$indX][$indY]; }
  function hasConnection($i, $j) { return $this->m_solver->g->hasConnection($i,$j); }
  function hasSolver() { return isset($this->m_solver); }

  function dubPuzzle() { return new Puzzle($this->m_solver,1) ; }

  function puzzleVar()     { return $this->m_puzzle;     }
  function solutionVar()   { return $this->m_solution;   }
  function solverVar()     { return $this->m_solver;     }
  function difficultyVar() { return $this->m_difficulty; }
  function symmetryVar()   { return $this->m_symmetry;   }
}

class SKBase {
  var $base;
  var $order;
  var $size;
  var $zerochar;
  var $type;

  function setorder($k,$type=0,$sized=-1){
#    debugStack();
    $this->type  = $type; // threedimensionalf
    $this->order = $k;
    $this->base  = intval(sqrt($k));
    if( $sized != -1 ) $this->size = $sized;
    else $this->size = ($this->type == 1)? $this->base*$this->base*$this->base : $this->order*$this->order;

    $this->zerochar = ( $this->order > 9 )? ord('a')-1: ord('0');
  }
  function __construct($i=9,$typef=0,$sized=-1) { $this->setorder($i,$typef,$sized); }
}

class SKPuzzle extends SKBase {
  var $numbers;
  var $flags;
  function __construct($oi=9,$typef=0,$sized=-1) {
    parent::__construct($oi,$typef,$sized);

    $this->numbers = array_fill(0,$this->size,0);
    $this->flags   = array();
    for($i=0;$i<$this->size;$i++)  {
      $this->flags[$i] = array_fill(0,$this->order+1,1);
#      $this->numbers[$i] = 0;
#      $this->flags[$i] = array();
#      for($j=0;$j<$this->order+1;$j++)
#	$this->flags[$i][$j] = 1;
    }
  }

  function randomize() {
    $disp = 0;
    for( $blk = 0; $blk < $this->base; $blk++ ) {
      $data = array();
      for($i=1;$i<=$this->order;$i++) { $data[] = $i; }
      for($i=0;$i<$this->base;$i++) {
	for($j=0;$j<$this->base;$j++) {
	  $idx = rand()% count($data);
	  $this->numbers[$disp + $j] = $data[$idx];
	  array_splice($data,$idx,1);
	}
	$disp += $this->order;
      }
      $disp += $this->base;
    }
  }
}

class SKGraph extends SKBase {
  var $optimized_d;	// Array of int 625
  var $optimized;	// Array of int 625x625
#  var $sc_count;	// int
  var $m_sizeX, $m_sizeY, $m_sizeZ;

  function __construct($o=9,$threedimensionalf=0) {
    parent::__construct($o,$threedimensionalf);

    $this->optimized_d = array(0,$this->size,0);
    $this->optimized   = array();
    for($i=0;$i<$this->size;$i++) {
      $this->optimized[$i] = array();
#      $this->optimized_d[$i] = 0;
    }
  }

  function sizeX() { return $this->m_sizeX; }
  function sizeY() { return $this->m_sizeY; }
  function sizeZ() { return $this->m_sizeZ; }
  function setSizeX($n) { $this->m_sizeX = $n; }
  function setSizeY($n) { $this->m_sizeY = $n; }
  function setSizeZ($n) { $this->m_sizeZ = $n; }
  function setSize ($n) { $this->size    = $n; }

  function hasConnection($i,$j) {
    for($k=0;$k<$this->optimized_d[$i];$k++)
      if( $this->optimized[$i][$k] == $j) return True;
    return False;
  }
  function addConnection($i,$j) {
    if( $this->hasConnection($i,$j) ) return;
    $this->optimized[$i][$this->optimized_d[$i]++] = $j;
  }
}

class GraphSudoku extends SKGraph {
  function __construct($o=9) { parent::__construct($o,0); }
  function init() {
    $this->m_sizeX = $this->order;
    $this->m_sizeY = $this->order;
    $this->m_sizeZ = 1;

    $row = 0; // int
    $col = 0; // int
    $subsquare = 0; // int
        
    for($i=0;$i<$this->size;$i++) {
      $row       = intval($i / $this->order);
      $col       = intval($i % $this->order);
      $subsquare = intval($col/$this->base) + intval($row/$this->base)*$this->base;
                
      $this->optimized_d[$i] = 0;

      for($j=0;$j<$this->order;$j++) {
	$this->addConnection($i, $j+$row*$this->order);
	$this->addConnection($i, $j*$this->order+$col);
	$this->addConnection(
	  $i,
	  (intval($subsquare/$this->base)*$this->base + intval($j%$this->base)) * $this->order +
	    intval($subsquare%$this->base)*$this->base + intval($j/$this->base));
      }
    }
  }
}

/* I do not know how to draw 3d sudoku in Firefox 
class GraphRoxdoku extends SKGraph {
  function __construct($o=9) { parent::__construct($o,True); }
  function init() {
  }
}
*/
class pos {
  var $x, $y, $z;
}

function RANDOM($x) { return mt_rand(0,$x-1); }

class Value  { var $firstLookup, $lastLookup; } // int, int
class Lookup {
  var $value, $prevLookup, $nextLookup; // uint, int, int
  function Set($value,$prevLookup,$nextLookup) {
    $this->value      = $value;
    $this->prevLookup = $prevLookup;
    $this->nextLookup = $nextLookup;
  }
}

class GroupLookup {
  private $m_values;		// Array Value
  private $m_lookups;		// Array Lookup
  private $m_valueCount;	// uint
  private $m_indexCount;	// uint

  function __construct() {
    switch(func_num_args()) {
    case 3: $this->GroupLookup_IndicesValuesDefaultvalue(func_get_arg(0),func_get_arg(1),func_get_arg(2)); break;
    case 2: $this->GroupLookup_IndicesValuesDefaultvalue(func_get_arg(0),func_get_arg(1),0); break;
    case 1: $this->GroupLookup_Lookup(func_get_arg(0));                     break;
    }
  }
  function GroupLookup_IndicesValuesDefaultvalue($indices,$values,$defaultValue=0) {
    $this->m_valueCount = $values;
    $this->m_indexCount = $indices;
                
    $this->m_values     = array();
    for($i=0;$i<$values;++$i) {
      $this->m_values[$i]  = new Value;
      $this->m_values[$i]->firstLookup = -1;
      $this->m_values[$i]->lastLookup  = -1;
    }
                
    $this->m_values[$defaultValue]->firstLookup = 0;
    $this->m_values[$defaultValue]->lastLookup  = $indices-1;

    $this->m_lookups    = array();
    for($i=0;$i<$indices;$i++) $this->m_lookups[$i] = new Lookup;
                
    /* !!!!
      Latter looks like
      for($i=0;$i<$indices-1;$i++) $this->m_lookups[$i]->Set($defaultValue,$i-1,$i+1);
      $this->m_lookups[$indices-1]->Set($defaultValue,$indices-2,-1);
    */
    $lookup = $this->m_lookups[$indices-1];
    $lookup->value      = $defaultValue;
    $lookup->prevLookup = $indices-2;
    $lookup->nextLookup = -1;
//    $lookup = $this->m_lookups[$indices-1-1];
    for($i = $indices-2; $i > 0; --$i, --$lookup) {
      $lookup = $this->m_lookups[$i];
      $lookup->value = $defaultValue;
      $lookup->prevLookup = $i-1;
      $lookup->nextLookup = $i+1;
    }
    $lookup = $this->m_lookups[0];
    $lookup->value = $defaultValue;
    $lookup->prevLookup = -1;
    $lookup->nextLookup = 1;
  }

  function GroupLookup_Lookup($lookup) { // C++ Copy constructor. I have carefully check for futher object cloning.
    $values  = $this->m_valueCount = $lookup->m_valueCount;
    $indices = $this->m_indexCount = $lookup->m_indexCount;
    $m_values     = array();
    for($i=0;$i<$values;$i++)  $m_values[$i]  = clone $lookup->m_values[$i];
    $m_lookups    = array();
    for($i=0;$i<$indices;$i++) $m_lookups[$i] = clone $lookup->m_lookups[$i];
    $this->m_values  = $m_values;
    $this->m_lookups = $m_lookups;
  }

  function __clone() { $this->GroupLookup_Lookup($this); }

  function firstIndexWithValue($value) {
    return $this->m_values[$value]->firstLookup;
  }
  function value($index) {
    return $this->m_lookups[$index]->value;
  }
  function setValue($index, $value) {
    $lookup = $this->m_lookups[$index];
                
    if($lookup->value == $value) return;
                
    if($lookup->prevLookup >= 0)
      $this->m_lookups[$lookup->prevLookup]->nextLookup = $lookup->nextLookup;
    else
      $this->m_values[$lookup->value]->firstLookup = $lookup->nextLookup;

    if($lookup->nextLookup >= 0)
      $this->m_lookups[$lookup->nextLookup]->prevLookup = $lookup->prevLookup;
    else
      $this->m_values[$lookup->value]->lastLookup = $lookup->prevLookup;
                
    $valueEntry = $this->m_values[$value];
    $lookup->value = $value;
    if($valueEntry->lastLookup >= 0) {
      $this->m_lookups[$valueEntry->lastLookup]->nextLookup = $index;
      $lookup->prevLookup = $valueEntry->lastLookup;
      $lookup->nextLookup = -1;
      $valueEntry->lastLookup = $index;
    } else {
      $valueEntry->lastLookup = $valueEntry->firstLookup = $index;
      $lookup->prevLookup = $lookup->nextLookup = -1;
    }
  }
}

class SolverState {
  var $m_size;		// uint
  var $m_order;		// uint
  var $m_values;	// QValueVector<uint>
  var $m_flags;		// QValueVector<QBitArray>
  var $m_remaining;	// GroupLookup

  function __construct() {
    switch(func_num_args()) {
    case 2: $this->SolverState_SizeOrder(func_get_arg(0),func_get_arg(1)); break;
    case 1: $this->SolverState_State(func_get_arg(0));                     break;
    }
  }
  function SolverState_SizeOrder($size,$order) {
    $this->m_size  = $size;
    $this->m_order = $order;
    $this->m_values = array_fill(0,$size,0);
#    for($i=0;$i<$size;$i++) $this->m_values[$i] = 0;
    $this->m_flags  = array(); 
    for($i=0;$i<$order;$i++) {
      $this->m_flags[$i] = array_fill(0,$size,True);
#      for($j=0;$j<$size;$j++)
#	$this->m_flags[$i][$j] = True;
    }
    $this->m_remaining = new GroupLookup($size,$order+1,$order);
  }
  function SolverState_State($state) { // object cloning
    $this->m_size  = $state->m_size;
    $this->m_order = $state->m_order;
    $this->m_values = array_merge($state->m_values);

    $m_flags  = array(); 
    for($i=0;$i<$state->m_order;$i++) {
      $m_flags[$i] = array_merge($state->m_flags[$i]);
    }
    $this->m_flags  = $m_flags;
    $this->m_remaining = clone $state->m_remaining;
#    print "<pre>\n==========Cloning SolverState=================\nSource============\n";
#    print_r($state);
#    print "Rezult=================\n";
#    print_r($this);
#    print "End =================\n</pre>\n";
  }
  function __clone() { $this->SolverState_State($this); }

  function value($index) { return $this->m_values[$index]; }

  function setValue($index, $value, $graph) {
    if($this->m_values[$index] != 0) return KSS_CRITICAL;

    $this->m_remaining->setValue($index, 0);
    $this->m_values[$index] = $value;

    for($i = 0; $i < $graph->optimized_d[$index]; ++$i) {
      $j = $graph->optimized[$index][$i];
      if($this->m_values[$j] == 0) {
        if($this->m_flags[$value-1][$j]) {
          $this->m_flags[$value-1][$j] = False;
          $remaining = $this->m_remaining->value($j);
          if($remaining == 1) return KSS_FAILURE;
          $this->m_remaining->setValue($j, $remaining-1);
        }
      }
    }
    return KSS_SUCCESS;
  }

  function fill($values, $graph) {//const QValueVector<uint>& ,SKGraph* 
    for($i=0; $i < $this->m_size; ++$i) {
      if($values[$i] == 0) continue;
      if($this->setValue($i, $values[$i], $graph) != KSS_SUCCESS)
        return KSS_FAILURE;
    }
    return KSS_SUCCESS;
  }
  /**
  * Sets all values for which only one flag is left
  * Returns wheter it failed due to conflicts.
  */
  function setAllDefindedValues($graph) {
    $state = 0;
    while(($index = $this->m_remaining->firstIndexWithValue(1)) >= 0) {
      for($i = 0; ; ++$i) {
        // Chekc whetere there wasn't a flag left
        if($i >= $this->m_order) return KSS_CRITICAL;
                                
        if($this->m_flags[$i][$index]) {
          if(($state = $this->setValue($index, $i+1, $graph)) != KSS_SUCCESS) return $state;
          break;
        }
      }
    }
    return KSS_SUCCESS;
  }

  function optimalSolvingIndex() {
    for($i = 2; $i <= $this->m_order; ++$i) {
      if($this->m_remaining->firstIndexWithValue($i) >= 0)
        return $this->m_remaining->firstIndexWithValue($i);
    }
    return -1;
  }
        
  function possibleValue($index, $startValue = 0) {
    if($this->m_values[$index] != 0) return 0;
    for($i = $startValue ? $startValue-1 : 0; $i < $this->m_order; ++$i) {
      if($this->m_flags[$i][$index])
        return $i+1;
    }
    return 0;
  }
}

class SKSolver {
  var $g;		// SKGraph
  var $base;		// int
  var $size;		// int
  var $order;		// int
  var $zerochar;	// int
  var $m_type = sudoku;
  public static $stack; 

  function __construct($n=9,$threedimensionalf=False) {
    if( is_object($n) ) { __construct2($n); return; } // Fixing overloaded constructors
    $this->base  = intval(sqrt($n));
    $this->order = $n;
    /*
      $this->m_type = $threedimensionalf ? roxdoku : sudoku;
      $this->size   = $threedimensionalf ? ($this->base*$this->order) : ( $n * $n );
    */
    $this->m_type = sudoku;
    $this->size  = $n * $n;

    if( !self::$stack ) { 
      self::$stack = array();
#      for($i=0;$i<625+1;$i++) self::$stack[$i] = new SKPuzzle();
    }
  }
  function __construct2($gr) {
    $this->base  = $gr->base;
    $this->order = $gr->order;
    $this->g     = $gr;
    $this->size  = $gr->size;

    $this->m_type = sudoku;
  }

  function type() { return $this->m_type; }
  function setType($type) { /* $this->m_type = $type;*/ }

  function get_simmetric($order, $size, $type, $idx, $which, &$out) {
    $out[0] = $idx;
    switch ($type) {
    case SIMMETRY_NONE:
      return 1;
    case SIMMETRY_DIAGONAL:
      if($which == 1)
        $idx = ($order-1-intval($idx/$order))*$order+$order-1-intval($idx%$order);
      $out[1] = intval($idx%$order)*$order+intval($idx/$order);
      return 2-($out[1]==$out[0]); // ???
    case SIMMETRY_CENTRAL:
      $out[1] = $size- $idx-1;
      return 2-($out[1]==$out[0]);
    case SIMMETRY_FOURWAY:
      $b = array(1,1,1);
      $out[1]=$out[2]=$out[3]=0;
      if(intval($order % 2) == 1) {
        if(intval($idx % $order) == intval(($order-1)/2)) $b[0] = $b[2]= 0;
        if(intval($idx / $order) == intval(($order-1)/2)) $b[1] = $b[2]= 0;
      }

      $c=1;

      if($b[2]==0) {
        $out[1] = ($order-1-intval($idx/$order))*$order+$order-1-intval($idx%$order);
        if($out[1] != $out[0]) $c++;
      }
      else {
        $out[1] = ($order-1-intval($idx/$order))*$order+$order-1-intval($idx%$order);
        $out[2] = intval($idx/$order)*$order+$order-1-intval($idx%$order);
        $out[3] = ($order-1-intval($idx/$order))*$order+intval($idx%$order);
        $c =4;
      }
                                
      /*printf("%d (%d %d) (%d %d) (%d %d) (%d %d)  - %d %d %d\n", c, idx%order, idx/order, 
                out[1]%order, out[1]/order, 
                out[2]%order, out[2]/order, out[3]%order, out[3]/order, b[0],  b[1], b[2]);*/
      return $c;
    }
    return 1;
  }

  function getSymmetry($flags, $index, &$out) {
    $solver = new Solver($this->g,$flags);
    return $solver->getSymmetry($index,$out);
  }
        
  function remove_numbers ($p, $level, $simmetry, $type) {
    $cnt=$p->size;
    $to=$p->size*(4+6*($level==-1));
    $solutions_d=0;

    $done = array();
    for($i=0;$i<$p->size+1;$i++){
#      self::$stack[$i]->setorder($this->order);
      $done[$i]=false;
    }
                
    if( $p->size > 81 ) $to =        ($p->size-32);
    if( $p->size >441 ) $to = intval(($p->size-32)/2);

    $c = new SKPuzzle($p->order);
    SKSolver::copy($c,$p);
        
    if($level < 0) $simmetry = SIMMETRY_NONE;

    if($simmetry == SIMMETRY_RANDOM) $simmetry = RANDOM(3)+2;
    $which = RANDOM(2);
    if($simmetry == SIMMETRY_FOURWAY && $this->order == 16) $to=intval($to/2);
    if($this->order == 25) $simmetry = SIMMETRY_NONE;

    for($q=0; $q<$to; $q++) {
      $idx     = RANDOM($p->size);  //2FIX
      $index   = array(0,0,0,0); //{idx, };
      $index_d = $this->get_simmetric($this->order, $this->size, $simmetry, $idx, $which, $index);
      $go      = True;
      for($i=0; $i<$index_d; $i++)
	if($this->g->optimized_d[$index[$i]]==0)
	  $go=False;
      if(!$go) {
        //printf("unlinked node %d %d\n",index_d,simmetry); 
        $q--; 
        continue;
      }
                
      $backup = array(0,0,0,0);
      $n_err = 0;

      for($i=0;$i<$index_d;$i++) {
        $backup[$i] = $p->numbers[$index[$i]];
        if($backup[$i] != 0 && $done[$index[$i]] == False) {
          $done[$index[$i]] = True;
          $solutions_d      = 0;
          $p->numbers[$index[$i]] = 0;
	  self::$stack[0] = new SKPuzzle();
	  self::$stack[0]->setorder($this->order);
          SKSolver::copy(self::$stack[0], $p);
          $this->solve_engine(0, $solutions_d, 0, 2, $index[$i], $index[$i], $backup[$i]);
          $n_err += ($solutions_d != 1);
        }
        else
          $n_err = 1;
      }
      if($n_err > 0)
        for($i=0; $i<$index_d; $i++)
          $p->numbers[$index[$i]] = $backup[$i];
      else    
        $cnt -= $index_d;
    }
    $numberOfNumbersToAdd = intval(
      (7*$level*((($type!=1) ? intval(sqrt($p->size)) : $p->order )+LEVINC-($p->order-2)*($type==1)))/10
    );
//  printf("%d\n", numberOfNumbersToAdd);

    for($i=0; $i< $numberOfNumbersToAdd; $i++) {
      $idx = RANDOM($p->size);//2FIX
      $orig = $idx;
      while($p->numbers[$idx] != 0) {
        $idx=($idx+1) % $p->size;
        if($idx==$orig) return $cnt;
      }
      $p->numbers[$idx] = $c->numbers[$idx];
      $index = array(0,0,0,0);
      $index_d = $this->get_simmetric($this->order, $this->size,$simmetry, $idx, $which, $index);
      for($j=0; $j<$index_d; $j++) {
        $p->numbers[$index[$j]] = $c->numbers[$index[$j]];
        $i++;    
        $cnt++;
      }
    }
    /* GENERATES PUZZLES WITH MULTIPLE SOLUTIONS
    if($level < 0) {
      for($i=0; $i<2; $i++) {
        $idx = RANDOM($p->size);//2FIX
        while($p->numbers[$idx] == 0) $idx=($idx+1) % $p->size;
        $p->numbers[$idx] = 0;
        $cnt--;
      }
    }*/
        
    return $cnt;
  }

  function remove_numbers2($p, $level, $simmetry, $typeo){
     $numbers = array();
        
     for($i = 0; $i < $size; ++$i)
       $numbers[$i] = $p->numbers[$i];
        
     $flags = 0;
        
     if($typeo == 1) $simmetry = SIMMETRY_CENTRAL;
     switch($simmetry) {
     case SIMMETRY_DIAGONAL: $flags |= KSS_SYM_DIAGONAL; break;
     case SIMMETRY_CENTRAL:  $flags |= KSS_SYM_CENTRAL;  break;
     case SIMMETRY_FOURWAY:  $flags |= KSS_SYM_FOURWAY;  break;
     default:
     case SIMMETRY_NONE:     $flags |= KSS_SYM_NONE;     break;
     }
        
     if($level == -1) $flags |= KSS_REM_1VALUE;
        
     $hints = $level*($this->order+LEVINC-($this->order-2)*$typeo);
     $this->removeValuesSimple($numbers, ($hints>0) ? $hints : 0, $flags);
        
     for($i = 0; $i < $size; ++$i)
       $p->numbers[$i] = $numbers[$i];
                
     return 1;
  }

  function removeValuesSimple(&$puzzle, $hints, $flags){
    $local = clone $puzzle;
    $cellsLeft = $this->size;
        
        // completely remove all occurences of a random value
    if($flags && KSS_REM_1VALUE) {
      $startValue = RANDOM($this->order)+1;
      for($i = $startValue; $i <= $this->order; ++$i) {
        $remCount = $this->removeValueCompletely($local, $i, $flags);
        if($remCount != 0) {
          $cellsLeft -= $remCount;
          break;
        }
      }
      if($i > $this->order) {
        for($i = 1; $i < $startValue; ++$i) {
          $remCount = $this->removeValueCompletely($local, $i, $flags);
          if($remCount != 0) {
            $cellsLeft -= $remCount;
            break;
          }
        }
        if($i == $startValue)
          return 0;
      }
    }
        
    $failures = 0;
    // scanning until order instead of base might remove about 2-5 more values
    while($failures < $base) {
      $startIndex = RANDOM($this->size);
      $index      = $startIndex;
      do {
        if($local[$index] != 0) break;
        $index = ($index+1)%$size;
      } while($index != $startIndex);
                
      $remCount = $this->removeAtIndex($local, $index, $flags);
      if($remCount != 0) {
        $cellsLeft -= $remCount;
        if($failures) --$failures;
      } else {
        ++$failures;
      }
      printf("Failures: %d - %d\n", $cellsLeft, $failures);
    }
        
    // give initial hints
    for($i = $hints; $i != 0; --$i) {
      $index = $startIndex = RANDOM($this->size);
      do {
        if($local[$index] == 0) {
          $local[$index] = $puzzle[$index];
          break;
        }
        $index = ($index+1)%$this->size;
      } while($index != $startIndex);
    }
    $cellsLeft += $hints;
        
    $puzzle = $local;
        
    return $cellsLeft;
  }

  function removeValues (&$puzzle, $count, $flags){
    $local = clone $puzzle;
    $removesLeft = $count;
        
    if($flags && KSS_REM_1VALUE) {
      $startValue = RANDOM($this->order)+1;
      for($i = $startValue; $i <= $this->order; ++$i) {
        $remCount = $this->removeValueCompletely($local, $i, $flags);
        if($remCount != 0) {
          $removesLeft -= $remCount;
          break;
        }
      }
      if($i > $this->order) {
        for($i = 1; $i < $startValue; ++$i) {
          $remCount = $this->removeValueCompletely($local, $i, $flags);
          if(remCount != 0) {
            $removesLeft -= $remCount;
            break;
          }
        }
        if($i == $startValue)
          return 0;
      }
    }
        
    while($removesLeft > 0) {
      $startIndex = RANDOM($this->size);
      $index = $startIndex;
      do {
        if($local[$index] != 0) {
          $remCount = $this->removeAtIndex($local, $index, $flags);
	  if($remCount != 0) {
	    $removesLeft -= $remCount;
	    break;
	  }
	}
	$index = ($index+1)%$this->size;
      } while($index != $startIndex);
    }
        
    $puzzle = $local;
    return 1;
  }

  function removeValueCompletely(&$puzzle, $value, $flags){
    $local = clone $puzzle;
    $count = 0;
        
    for($i = 0; $i < $this->size; ++$i) {
      if($local[$i] == $value) {
        $remCount = $this->removeAtIndex($local, $i, $flags);
        if($remCount == 0) return 0;
        $count += $remCount;
      }
    }
        
    $puzzle = $local;
    return $count;
  }

  function removeAtIndex(&$puzzle, $index, $flags){
    $indices   = array(0,0,0,0);
    $oldValues = array(0,0,0,0);
        
    $count = $this->getSymmetry($flags, $index, $indices);
    for($i = 0; $i < $count; ++$i) {
      $oldValues[$i] = $puzzle[$indices[$i]];
      $puzzle[$indices[$i]] = 0;
    }
        
    $state = new SolverState($this->size, $this->order);
    for($i = 0; $i < $this->size; ++$i) {
      if($puzzle[$i]) $state->setValue($i, $puzzle[$i], $g);
    }
        
    $forksLeft = $this->size * 8;
    $solutionsLeft = 2;
    $this->solveEngine($state, 0, $solutionsLeft, $forksLeft);

    if($solutionsLeft == 1) return $count;
        
    for($i = 0; $i < $count; ++$i)
      $puzzle[$indices[$i]] = $oldValues[$i];

    return 0;
  }

  function solve($puzzle,  $max_solutions=1, $out_solutions=NULL, &$forks=NULL){ //SKPuzzle* , int , SKPuzzle* , int*
    if( $puzzle->order != $this->order ) return -1;
    if( $puzzle->size  != $this->size  ) return -1;
    if( !isset($this->g) ) return -2;
        
    $mySolver = new Solver($this->g);

    $v = array(); // uint

    for($i = 0; $i < $this->size; ++$i)
      $v[$i] = $puzzle->numbers[$i];

    $ret = $mySolver->solve($v, $max_solutions);
    if($ret < 1) return -3;

    if(isset($out_solutions)) {
      $values = $mySolver->result();
      for($i = 0; $i < $this->size; ++$i)
        $out_solutions->numbers[$i] = $values[$i];
    }

    return $ret;
  }

#  function solve2($puzzle,  $max_solutions=1, $out_solutions=NULL, &$forks=NULL){ //SKPuzzle*, int, SKPuzzle*, int*
#    if($puzzle->order != $this->order) return -1;
#    if( !isset($this->g) ) return -2;
#    $solutions_d=0;
#        
#    $ffs = 0;
#    if(!isset($forks)) $forks = $ffs;
#
#    $head = self::$stack[0];
#    for($i=0;$i<$puzzle->size+1;$i++) {
#      self::$stack[$i]->setorder($this->order, $puzzle->type);
#      self::$stack[$i]->size = $puzzle->size;
#    }
#    for($i=0;$i<$puzzle->size;$i++)
#      if($this->g->optimized_d[$i]==0) 
#        $puzzle->numbers[$i]=1;
#
#    SKSolver::copy(self::$stack[0], $puzzle);
#    $this->solve_engine(0, $solutions_d, $out_solutions, $max_solutions, -1, -1, 0, $forks);
#    if($puzzle->order == 25 && $puzzle->type==0 && $forks > MAX_FORKS) {
#      if($max_solutions <= 1) return -3;
#                
#      for($i=0;$i<$puzzle->size;$i++) $puzzle->numbers[$i] = 0;
#      $this->solve($puzzle, 1, $puzzle,0);
#    }
#    if($forks) printf("%d\n", $forks);
#        
#    return $solutions_d;
#  }

#  function solveEngine($state, $puzzle, &$solutionsLeft, &$forksLeft){ // SolverState&,SKPuzzle*,uint*,uint*
#    if(($ret = $state->setAllDefindedValues($this->g)) != KSS_SUCCESS) return $ret;
#        
#    $index = $state->optimalSolvingIndex();
#    // Are there no more free fields?
#    if($index < 0) {
#      if($puzzle) {
#        for($i = 0; $i < $this->size; ++$i)
#          $puzzle->numbers[$i] = $state->value($i);
#      }
#      if( $solutionsLeft -- <= 1)
#        return KSS_ENOUGH_SOLUTIONS;
#      return KSS_SUCCESS;
#    }
#        
#    $startValue = RANDOM($this->order);
#    $restart = False;
#    $value = $state->possibleValue($index, $startValue);
#    if(!$value) {
#      $restart = True;
#      $value = $state->possibleValue($index, 0);
#    }
#    // Reached a fork
#    while($value) {
#      // Takes the next path
#      $localState = new SolverState($state);
#                
#      if( $forksLeft-- == 0)
#        return KSS_ENOUGH_FORKS;
#                
#      // Setup the path
#      if(($ret = $localState->setValue($index, $value, $this->g)) != KSS_SUCCESS) return $ret;
#                
#      // Process the path
#      $ret = $this->solveEngine($localState, $puzzle, $solutionsLeft, $forksLeft);
#      switch($ret) {
#      case KSS_CRITICAL:
#        return KSS_CRITICAL;
#      case KSS_ENOUGH_SOLUTIONS:
#        return KSS_ENOUGH_SOLUTIONS;
#      case KSS_ENOUGH_FORKS:
#        return KSS_ENOUGH_FORKS;
#      case KSS_SUCCESS:
#      case KSS_FAILURE:
#        break;
#      }
#                
#      $value = $state->possibleValue($index, $value+1);
#      if(!$value && !$restart) {
#        $restart = True;
#        $value = $state->possibleValue($index, 0);
#      }
#      if($restart && $value >= $startValue) return KSS_SUCCESS;
#    }
#        
#    // This path finished
#    return KSS_SUCCESS;
#  }

  function init() {
    if     ($this->m_type==0) $this->g = new GraphSudoku($this->order);
#    else if($this->m_type==1) $this->g = new GraphRoxdoku($this->order);
    else { die( "Wrong game type" ); }

    $this->g->init();

    $this->zerochar = ($this->order > 9 )? (ord('a')-1): ord('0');
                
    return 0;
  }

  static function copy($dest, $src){ // SKPuzzle*, SKPuzzle*
    $dest->order = $src->order;
    $dest->base  = $src->base;
    $dest->size  = $src->size;

    $dest->numbers = array_merge($src->numbers);
    $flags = array();

    for($i=0; $i<$src->size;$i++) {
      $flags[$i] = array_fill(0,$src->order+1,1); 
#      $dest->numbers[$i] = $src->numbers[$i];
#      for($j=0; $j<$src->order+1; $j++)
#        $dest->flags[$i][$j] = 1;  //src->flags[i][j];
    }
    $dest->flags = $flags;
  }

        
        
        
  function solve_engine(
               $stackIndex,  &$solutions, $solution_list=NULL, $maxsolutions=1, $last_add=-1,
    // SKPuzzle *,         int&,           SKPuzzle*,             int,          int,
    $dynindex=-1, $dynvalue=0, &$forks=NULL
    //       int,         int,          int* 
  ){
    $pzlStkElt = self::$stack[$stackIndex];
    if(isset($forks) && $pzlStkElt->order == 25 && $pzlStkElt->type==0) {       
      if(($forks) > MAX_FORKS) return -1;
    }
        
    if($maxsolutions>0 && $solutions>=$maxsolutions)   return 0;
        
    if($dynindex!=-1)
      if($dynvalue == $pzlStkElt->numbers[$dynindex]) {
        $solutions++;
        return 1;
      }

    $lowest_pos = 0;
    $lowest_val = 0;

    $lowest = $pzlStkElt->order+1;
                
    for($i=$last_add*($last_add != -1); $i<($last_add+1)+$pzlStkElt->size*($last_add == -1); $i++)
      if($pzlStkElt->numbers[$i] != 0)
        for($j=0; $j<$this->g->optimized_d[$i]; $j++)
          if($pzlStkElt->numbers[$this->g->optimized[$i][$j]] == 0)
            $pzlStkElt->flags[$this->g->optimized[$i][$j]][$pzlStkElt->numbers[$i]] = 0;
                
    for($q=0; ($last_add==-1) ? $q<$pzlStkElt->size : $q<$this->g->optimized_d[$last_add]; $q++) {
      $i = ($last_add==-1) ? $q : $this->g->optimized[$last_add][$q];
      if($pzlStkElt->numbers[$i] == 0) {
        $c=0;
        for($j=0;$j<$pzlStkElt->order;$j++)
          $c += $pzlStkElt->flags[$i][$j+1];
                                
	if($c < $lowest) {
	  $lowest_pos = $i;
	  $lowest     = $c;

	  if($lowest < 1)
	    return -1;
	  if($lowest == 1) {
	    for($j=0;$j<$pzlStkElt->order;$j++) if($pzlStkElt->flags[$lowest_pos][$j+1] == 1) $lowest_val = $j+1;
	    $pzlStkElt->numbers[$lowest_pos] = $lowest_val;

	    return $this->solve_engine($stackIndex, $solutions, $solution_list, $maxsolutions, $lowest_pos,-1,0,$forks);
	  }
	}
      }
    }
        
    if($last_add != -1)
      return $this->solve_engine($stackIndex, $solutions, $solution_list, $maxsolutions, -1,-1,0,$forks);
        //check completed
    $remaining=0;
    for($i=0;$i<$pzlStkElt->size;$i++) if($pzlStkElt->numbers[$i] == 0)$remaining++;

    if($remaining == 0) {
      if(isset($solution_list)) 
	if(isset($solution_list[$solutions])) 
	  SKSolver::copy($solution_list[$solutions], $pzlStkElt);
      $solutions++;
      return 1;
    }
        
    if($remaining == 1) return -1;
        
    //fork on lowest if not added
    $positions = array(); //2fix
    $positions_d = 0;
        
    for($i=0;$i<$pzlStkElt->order;$i++)
      if($pzlStkElt->flags[$lowest_pos][$i+1])
        $positions[$positions_d++] = $i+1;
                        
    while($positions_d>0) {
      self::$stack[$stackIndex+1] = new SKPuzzle;
#      self::$stack[$stackIndex+1]->setorder($this->order);
      SKSolver::copy(self::$stack[$stackIndex+1], $pzlStkElt);
      $index = RANDOM($positions_d);
      self::$stack[$stackIndex+1]->numbers[$lowest_pos] = $positions[$index];
      $this->solve_engine($stackIndex+1, $solutions, $solution_list, $maxsolutions, $lowest_pos, -1, 0, $forks);

      if(isset($forks)) $forks++;
      for($i=$index; $i<$positions_d-1; $i++) $positions[$i] = $positions[$i+1];
      $positions_d--;
    }
        
    return -1;      
  }

  function addConnection($i, $j){ // int, int
    for($k=0;$k<$this->g->optimized_d[$i];$k++) {
      if($this->g->optimized[$i][$k] == $j)
        return;
    }
    $this->g->optimized[$i][$this->g->optimized_d[$i]++] = $j;
  }
}

class Solver {
  private $m_solutionsLeft;	// uint
  private $m_forksLeft;		// uint
  private $m_flags;		// uint
  private $m_graph;		// SKGraph*
  private $m_result;		// QValueVector<uint>

  function __construct($graph,$flags=0) { // SKGraph*, uint
    $this->m_graph = $graph;
    $this->m_flags = $flags;
    $this->m_result = array();
  }

  function solve($puzzle, $maxSolutions = 1) { //const QValueVector<uint>& ,uint
    // I got constant values in this method by trial and error
        
    $state = new SolverState($this->m_graph->size, $this->m_graph->order);
    $state->fill($puzzle, $this->m_graph);
        
    $result = -1;
        
    // Do 20 tries to solve the puzzle, this should be enough in most cases
    for($i = 0; $i < 20; ++$i) {
      // TODO This might change whith an evolved internal solver algorithmn
                
      // If no solutions were found after size*8 forks, than there 
      // will probably be no solution in a near range, and a restart of the
      // solving will give the solutions faster.
      // After the first solution was found the next solutions
      // are within few forks.
      $this->m_forksLeft = $this->m_graph->size * 8;
        
      $this->m_solutionsLeft = $maxSolutions;
      $result = $this->solveByForks($state);
      if($result != KSS_ENOUGH_FORKS) break;
    }
        
    switch($result) {
    case KSS_ENOUGH_SOLUTIONS:	return $maxSolutions;
    case KSS_SUCCESS:		return $maxSolutions - $this->m_solutionsLeft;
    case KSS_ENOUGH_FORKS:
    case KSS_FAILURE:		return -1;
    default:
    case KSS_CRITICAL:		return -2;
    }
  }

  function result() { return $this->m_result; }

  function getSymmetry($index, &$out) { // uint, uint[4]
    $which = 0; // TODO replace this with another flag
        
    $out[0] = $index;
    switch($this->m_flags & KSS_SYM_MASK) {
    case KSS_SYM_NONE:
      return 1;
    case KSS_SYM_DIAGONAL:
      if($which == 1)
        $index = ($this->m_graph->order -intval($index/$this->m_graph->order) -1) * $this->m_graph->order +
          $this->m_graph->order -$index%$this->m_graph->order -1;
      $out[1] = ($index % $this->m_graph->order) * $this->m_graph->order + intval($index/$this->m_graph->order);
      return 2 - (($out[1]==$out[0]) ? 1 : 0);
    case KSS_SYM_CENTRAL:
      $out[1] = $this->m_graph->size -$index -1;
      return 2 - (($out[1]==$out[0]) ? 1 : 0);
    case KSS_SYM_FOURWAY:
      $b = array(1,1,1);
      $out[1] = $out[2] = $out[3] = 0;
      if($this->m_graph->order & 0x1 == 1) {
        if( intval($index % $this->m_graph->order) == intval(($this->m_graph->order -1)/2) ) $b[0] = $b[2] = 0;
        if( intval($index / $this->m_graph->order) == intval(($this->m_graph->order -1)/2) ) $b[1] = $b[2] = 0;
      }
                        
      $c = 1;
      if($b[2] == 0) {
        $out[1] = ($this->m_graph->order -1 -intval($index/$this->m_graph->order))*$this->m_graph->order +
          $this->m_graph->order - 1 - $index%$this->m_graph->order;
        if($out[1] != $out[0]) $c++;
      } else {
        $out[1] = ( $this->m_graph->order -1 -intval($index/$this->m_graph->order) )*$this->m_graph->order +
          $this->m_graph->order -1 -$index%$this->m_graph->order;
        $out[2] = intval($index/$this->m_graph->order)*$this->m_graph->order +
          $this->m_graph->order-1-$index%$this->m_graph->order;
        $out[3] = ($this->m_graph->order -1 - intval($index/$this->m_graph->order))*$this->m_graph->order +
          $index%$this->m_graph->order;
        $c = 4;
      }
      return $c;
    }
    return 1;
  }

  function solveByLastFlag($state) { // SolverState& 
    if(($ret = $state->setAllDefindedValues($this->m_graph)) != KSS_SUCCESS) return $ret;
        
    $index = $state->optimalSolvingIndex();
    if($index < 0) {
      for($i = 0; $i < $this->m_graph->size; ++$i)
        $this->m_result[$i] = $state->value($i);
                
      if($this->m_solutionsLeft-- <= 1)
        return KSS_ENOUGH_SOLUTIONS;
    }
    return KSS_SUCCESS;
  }

  function solveByForks($state) { // SolverState& 
    if(($ret = $state->setAllDefindedValues($this->m_graph)) != KSS_SUCCESS) return $ret;
        
    $index = $state->optimalSolvingIndex();
    // Are there no more free fields?
    if($index < 0) {
      for($i = 0; $i < ($this->m_graph->size); ++$i)
        $this->m_result[$i] = $state->value($i);
      // if we have enough solutions end searching for other solutions
      // this code would secure against preset *solutionsLeft == 0.
      if($this->m_solutionsLeft-- <= 1)
        return KSS_ENOUGH_SOLUTIONS;
      return KSS_SUCCESS;
    }
        
    $ret = $this->solveByLastFlag($state);
    if($ret != KSS_SUCCESS) return $ret;
        
        
    $startValue = rand() % $this->m_graph->order;
    $restart = false;
    $value = $state->possibleValue($index, $startValue);
    if(!$value) {
      $restart = true;
      $value = $state->possibleValue($index, 0);
    }
    // Reached a fork
    while($value) {
      // Takes the next path
      $localState = new SolverState($state);
                
      if($this->m_forksLeft-- == 0)
        return KSS_ENOUGH_FORKS;
                
      // Setup the path
      if(($ret = $localState->setValue($index, $value, $this->m_graph)) != KSS_SUCCESS) return $ret;
                
      // Process the path
      $ret = $this->solveByForks($localState);
      if($ret != KSS_SUCCESS && $ret != KSS_FAILURE) return $ret;
                
      $value = $state->possibleValue($index, $value+1);
      if(!$value && !$restart) {
        $restart = true;
        $value = $state->possibleValue($index, 0);
      }
      if($restart && $value >= $startValue) return KSS_SUCCESS;
    }
        
    // This path finished
    return KSS_SUCCESS;
  }
}


/// ---------

//main code line

#  header('Content-type: text/html; charset=UTF-8');
#  header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
#  header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past
#
#
#<html>
#  <head>
#    <title>Провека построения Судоку</title>
#  </head>
#  <body>
#

#$ishStr = array(
#  '901000802002000700400000005640209071080703020000405000000907000710000083004000200',
#  '002030008000008000031020000060050270010000050204060031000080605000000013005310400'
#);

#$ishStr = $ishStr[0];

#prtSudoku(str_split($ishStr));


#$solver = new SKSolver(9,0);
#$solver->init();

#$puzzle = new Puzzle($solver);
#print "<pre>\n";
#print_r($puzzle);
#print "</pre>\n";
#$CurDebug = 1;
#$puzzle->init(str_split($ishStr),0);
#$puzzle->init(2,SIMMETRY_DIAGONAL);
#prtSudoku($puzzle->m_puzzle);
#prtSudoku($puzzle->m_solution);


#prtSudoku($puzzle->m_solution->numbers);
#print "<pre>\n";
#print_r($puzzle);
#print "</pre>\n";
#  </body>
#</html>

function generateSudoku($level=1,$symm=0) {
  $solver = new SKSolver(SUDOKUORDER,0);
  $solver->init();
  $puzzle = new Puzzle($solver);
  $puzzle->init($level,$symm);
  return array( 'puzzle'=>$puzzle->m_puzzle, 'solution'=>$puzzle->m_solution);
}


function otdatiPNG($pngBase64) {
  $cifra = base64_decode($pngBase64);
  header('Content-type: image/png');
  header('Content-Length: '.strlen($cifra));
  header('Last-Modified: Wed, 20 Oct 2010 00:00:00 GMT');
  print $cifra;
}


function otdatiCifru() {
  if(
    !isset($_REQUEST['Cifra'],$_REQUEST['Variant']) ||
    intval($_REQUEST['Cifra']) < 1 || intval($_REQUEST['Cifra']) > SUDOKUORDER ||
    intval($_REQUEST['Variant']) < 0 || intval($_REQUEST['Variant']) > SUDOKUORDER-1
  ) {
    header("HTTP/1.0 404 Not Found");
    exit(0);
  }

  $cifry = sozdatiCifry();

  otdatiPNG($cifry[intval($_REQUEST['Cifra'])][intval($_REQUEST['Variant'])]);
}


function otdatiKrest() {
  otdatiPNG(sozdatiKrest());
}


function sozdatiKrest() {
  $krest = 
    'iVBORw0KGgoAAAANSUhEUgAAAIAAAACACAYAAADDPmHLAAAAAXNSR0IArs4c6QAAAAZiS0dEAP8A'.
    '/wD/oL2nkwAAAAlwSFlzAAALEwAACxMBAJqcGAAAAAd0SU1FB9oKDQcqBzPrGpsAAAAdaVRYdENv'.
    'bW1lbnQAAAAAAERtaXRyeSBZLiBLYXphcm92ddNELgAADDZJREFUeNrtnX+UVVUVxz8z6uEmWIBr'.
    'USLGS8NgChc5KerCwKwQkiWJgmCKYVngDyLFwtIMDUEiRUJN0AA1TUwx+eWPlVpomoCIC9Rg4aiA'.
    'SCAtG+xyGmb6Y+9pTaz5de6777177zvftd4/8M6Zd8/+3n322b8OeHh4eHh4eHh4eHh4eHh4eHh4'.
    'eGQfFX4J0o/QmM5ADugH9NR/fhtYB9QE1v7TEyDbwh8OnKUEyOl/1SgBHgOWtESCg/0SZkL4k4Gq'.
    'A/471+RDaEyzJKj0y5hq5IBvNSP8puin2iHX3H96AqT37e8OfB8Y2I6vf0mJUNwtIB/jxKNN4f8Q'.
    'OK+dMuzRZP2LQ4D2GCct7Usera5rf+BaYHAc8ju4wMKf2IzqaTRMjm3NOPFodl1zwC+A0yMMf7uY'.
    'NkCuyZvfEqqA0S0ZJx7NCn9mROGjWrdoBOinhkdbGKCqzKNtDAFOizi2Rj9FI0BPNTzawqHA0NAY'.
    '4+Xb5r7/M+DwiFP8rqVtNgnHwJOAMz0JWhX+rUC3iFO8ATxaVCOwJYOjBRjd2+pDY5YH1lov9v/t'.
    '+ecA1wBdIk6zF/g18HqxCbDO8ftHA7cBlaExS8uZBHrGHwL8BPhMxGnqgd3AjcCCwNq9LX2xokAP'.
    '0Rn4K9A7grEyFbi/3EgQGtNRDeIfqQEddXveDywG5gMvtib8ghFAH2gCMDfC0D3AhcCywNqGMhF+'.
    'FXAxMB4I8pDLh8D1wNz2vkCFNAKXA5sjjOsCLATOCI2pKBPhzwUuAz6W50v5AvCoi/YsGAECa2uA'.
    '6UAUVd4V8XX3yDIJQmOGAfcDg9QYztfuukbXnZITQLFCP3URxn5V1eLHs2johcZMAn5P697S9mIz'.
    'cAOwwXVgQQkQWLsduKm1Y0gbuAiozooWCI0xoTEDgEXAL1Xl54stajhGOj0V3BEUWPsS8F1gdYTh'.
    'PZFslx4ZEH5XJHy7EHHpxrH2zwPfiSr8gp4CmlmA/nou/Yrjw3+ERMBuT2vUUIU/WTXap2KYchcw'.
    'A3jYdc8vGQF0IXohDp8zHIe+AVwSWPuXFAr/eDVoz49hug+BOcDCwNpNcfy+ihIsSC/gV8CZDsPq'.
    '1LkxIS1aQJ1hg4BbyD/kXYf48+8EVsXpJKso4VvxjKOFvxsYHVj7VAoE3xe4QjVdpzynfFvthvnA'.
    '+3F7SCtKuFBX697e3nhEgxo9o/R0kTTBV6ixeoF+egEH5Tntq0hW1eq2XLpRUcpw8B/1CONC1lOA'.
    'cUkLHavw+6hhNgWJgeQr/HuAMYG1zxVK+CXVALpww4F7HdVkCEwgIQEjFf45KvgvxjDlTiQ8fk9g'.
    '7QeF/v2lTgh5FliJRLDa7VoArgMGl1oThMb0Bm4GHopB+PXAKj3X31YM4ZdcA+gingrchXvoeCPw'.
    'vcDaVSW08GchuQz5Yh+wTIm9qZiaLQkpYa8hPvHQcdzngGs0gaJoglfCztOtKw7h71U/weWBtRuK'.
    'va2VnAB6rl+kW4ELDkJSpIcU8a0fg+TnDY/heAcSvj0XuKNUJ5tEBFl0Lx+o590jHIfvAM4t5FYQ'.
    'GnMkcBXiyu0cw5S1wOOIa/z1Uia+JCbKpiS4Qi3gKM6Sq5Esor0x/qaOSO3Cj3XPzxf7gU26fdwL'.
    'bAusrS/luicqzKpBk+lI9DDK4q5EYg3P50sEDdtejJRfx5E8u1d/32zgtaS4tBMXZ9dYwXx986La'.
    'KM8AtwMrA2trHf9+NRK2naiCj2ONtgBXAs8mLZaRyESL0JgTkajXiXlOtUznWdWWRtA8/K/ruf6w'.
    'GA3kWcD8wNo3krjWSSWAQVKk7yP/lLBatdwXBtZubuZvdQdORtLRewGHxPQYryDZUA8nObs5salW'.
    'SoLrkAKJvKfT/XdyIwnUi/c1te6PiFHwibHwU02AJvbA3cCpMUxngQXAk0hK1qUx/9zEWfipJ0AT'.
    'a/y3wGdjmG4f0KEAP9Mi2c+zkmThZ4IATUgwKwajsBDYo9pkRRpzFlOTbq0VNJfp2TwJ+QD1SFHH'.
    'XaUISJUdAZQEHYGzgVFIDKBUsYyndZ//QyGTNTwBWibBJ4ERaswNLiIR1qoROR/YkXbhp5IABxDh'.
    'MCTxcgxSSlao53kX6bk7G0nM/BcZQVZKroYCvyH+CqIPgfVIMGh9lgSfCQKoF+8sxH3bKcapa4Ft'.
    'SHuVJ+IqwvAEiE/wnYETEE9hf+Lz4r2LpJo9iHQ4eSvrnUrSdgowquYvRkqtPh3TMzQglUdXqtqv'.
    'TYMXLw4cnCLBH64W/1ikPsDE/CIMAjoF1m6ljFCREuGfhPQNGqmWf6FwN3BVOfUuTnowqCswDpgE'.
    'FCv7dwYwvVxIkORwcJXuyeOK/Kd3Ih03yqKLeWUCBd89NGYMks0zrgQ/oRtS6pXzRmBxBZ8DqvXt'.
    '6xPjuf4VJJTsYjucrIbmOq8BivPGjwCWqBF2QkzCfw/JMB6FVNq6oKsant4GKKDgG505U5DWqHFZ'.
    '9xZ4E+k98DTwgarzxaph2outQP8k9iJINQGaceYcFZMmalBhP4IUm65v9OI1KeF+yHHOqcD1WW5Z'.
    'W1EC4Q9E6vuHEF96Voh0D5kBvNyc9R4aU4kkaw51nHskCc/sTYURGBrTBSm4uDpGC7sBScScBjzV'.
    'mroOrK0PjVkGfNnRxvgpsCU0Zm0WSVBZJOF3Q/LmZsYo/H+rn+C0wNqF7dyrX4hg2R+nmuVYfwqI'.
    'Lvwp+ukY49FuVGDtLY5GWg2wVMnjgoHAdcXsRZAJAmjWzlik7cmhMUy5G3EQjQqsfdx1sNoGi4En'.
    'ImyVI5DcA28EOhDgbCSnP9/yrhB4B6n8fTKfBI0mhug8WrhOtRW8DwxKap1fojSA9ga+KU/hh2rk'.
    'TQSGAfPyzc7Ro+FzROtD0A3p6OE1QBvCH4AkUB4fcYo6fdtuBR4rREqWaoLZyA3cLtinR8lVWcgW'.
    'qiiQ8KOWcrXozCkQUXsjDaqOi2CE/lx9D7vTfDysiHlBq1T4UUq46oGXkBsyXy5WKDY05iTEQ3hU'.
    'BIP0PtUiNWklQWWMC9kRKd2KIvz/AHcAFwbWPlXkOPxaJGhU6zjucLVNbgb6pPVWk8oYhf8NosXv'.
    '9yM1dlOba+BQaOgW8xDiJo6CcxBvYY9y1gCdkMqcKL795UgK1s4SrsNuVeVvRRw/DLhAI5xlSYC+'.
    'SIt0V2xQS/+tUi6C7t8b89ACnfT5+5YdAfQ49U2kibMLtiCFHYk4TmnZ11wkXhAFxwBj06YFKmMQ'.
    '/vmIq9cFG8nztqsCYTvwQMSxhyCtbHJlQQAVfjXSZMmlSOM9pMv3M0lzpGhPwQVIyDoKjkRK1cpC'.
    'A+SAy4Eqx3Gzk9xRQ0lwBxLEcvX5dywnAhwLfMFxzDtIM6VEI7C2NrB2EdKNfBpuV9/myoUAp0ew'.
    'eu9Dyq5TAc01mIncbNJefL5cCNA5wtu/FEhVkwX1Sj7oMOQT6hLPPAGOcfz+HGBNSiNobzp8twNw'.
    'Z1pIkA8BXIIff0duwUpr+NQ1HH0qMDcNJIhEAH0wl1uy9hTrFqwCoTbCmFOA8ZoNnR0ChMZ0An6A'.
    'WyXP6ykWPtoObprjMANcApyXtIsu89UAI3G/0SMLZdZR7iw2iKOsOqkkqHR8+3O4t2/fivTYI+Va'.
    '4FnEQeSKoyl8Z5OiaYDhuN+VtwvJx88C7gb+EWHcRcRzrWzpCKBFEZMc598BzAiszQQBAmvXIEWt'.
    'ri1iOwPfTqJB6KIBqpG2bC5YBbxIhqAFKeORoJYLRgCXJo0ElY4P4IKXkStaasgeHkH6D7hcd9sB'.
    'uSL2bE2hSx0BXJI964AbMir8xmPhAqRSyQVdgGtxa1SRGAK4sPaFKLV7KSTBPKSAxQWHIaVpqSOA'.
    'SxBnF2UAzWKe7jisq56mUkeA3Q7fbaB8sATJb0wlXAjwkcN3DykX6audM81hffYjzShTRwAXg247'.
    '5YUVSHFJe6KdO5FCmNQRYBtSGdsW9pGirJ+YtMB2PRYup/XuI3XAq8DqNBLgT0g1bFt4Xr9LmZFg'.
    'MzBZt4Nd/L+3cD/iOHoamJSkBhMuXcJqgL8B/dSSbQ4f6HdqKEME1m4OjbkF+LMe9YYfoPZXJ627'.
    'iFNFq8YDLgdGI9Wxje3WavWU8AAwJ+vdNbME55JmJUE1UhDZ6NFag9TVrfHC9/Dw8PDw8PDw8PDw'.
    '8PDw8PDw8PDw8PDw8PDw8PDw8PDw8Cgh/gsWlfuYpzqc0QAAAABJRU5ErkJggg==';

  return $krest;
}

function sozdatiCifry() {
  $cifry = array();
  for( $i=1; $i<=SUDOKUORDER; $i++ ) $cifry[$i] = array();

  $cifry[1][0] = 
    'iVBORw0KGgoAAAANSUhEUgAAAIAAAACACAYAAADDPmHLAAAAAXNSR0IArs4c6QAA'.
    'AAZiS0dEAP8A/wD/oL2nkwAAAAlwSFlzAAALEwAACxMBAJqcGAAAAAd0SU1FB9oK'.
    'EQgEDL6tZSUAAAAdaVRYdENvbW1lbnQAAAAAAERtaXRyeSBZLiBLYXphcm92ddNE'.
    'LgAAAflJREFUeNrt3T1yAjEQRGGJ0jV8BzLfPyTzHTiInZvCP2V5Yaa/F5Lg2n7T'.
    'khbwjgEAAAAAAFKYLsHveHl9e//82vVyngQIDb+6BATYEH5lCU6i3RN+VQgQHD4B'.
    'wsMnAAiQPP0ECA+fAOHhE2AjVW8EESB4+gkAAiRPPwHCwydAePhjjLEEbw8gfA0g'.
    'eA0g/Eim8P9O5e8ELsFbAoRvCRB8Yv23awBTHyzArvCvl/OsPtVRm8CdU58UfAsB'.
    'dk596lKyTP05+tdRy9TbBAo/WKK4TwO/Cy3tKLlSpl/lFxVgx0QKv6gAR0994p3E'.
    'ZeqzW2WlT71joPAJ0OmMf8T7dxKtzX0A019YANWvAR4afvIXSVZy8NqmaAMIP7QB'.
    'rPVNG+AnwQq/+RLwVcDCD1kCBG0TCAKAACAACAACgAAgAAgAAoAAIAAIAAIcT9dP'.
    'KgmgAUAAEAAEAAFAABAABAABQAAQoDvpD5nQABoABAABQADcofPP1gmgAUAAEAAE'.
    'iMOjZjWABnAJCAACgAAgAG7p/t9LYwVwBNQAIAAiBVD/GgAEwBhjxD2gIfUJoRoA'.
    'eQ3wqM1epeaYgsuWYAo/W4KT8B0DQQAQAARA3imAAO4DOAZWD7FVA/znhfdw6lvW'.
    'M/5RgrIHQOoe4Bn2GUkNFFu19ySw/AAAAAAAGvMBv8nRPdh+wGAAAAAASUVORK5C'.
    'YII=';

  $cifry[1][1] = 
    'iVBORw0KGgoAAAANSUhEUgAAAIAAAACACAYAAADDPmHLAAAAAXNSR0IArs4c6QAA'.
    'AAZiS0dEAP8A/wD/oL2nkwAAAAlwSFlzAAALEwAACxMBAJqcGAAAAAd0SU1FB9oK'.
    'EQgWNgBVzEQAAAAdaVRYdENvbW1lbnQAAAAAAERtaXRyeSBZLiBLYXphcm92ddNE'.
    'LgAAAWZJREFUeNrt3TEOgkAQhlHWeA3vQOf9SzrvwEGwodMYVonJ/vNeael8OyKJ'.
    'Mk0AAAAAAAAAAABAguYteHW7P7Z3r6/L3ARQdPipEQigY/iJEVyMvW/4aQRQePgC'.
    'QAACsP43ASAAp18ACAABWP8CQABOvwAQAAKw/gVg+AJAAE6/ABCA0y8ABOD0C8Dw'.
    'BWD4AsgZfuLPvkoG4OTbAJPTf9y1+smvPPyYDWDtFw7gl+FXP/3DB2D4xa4Bzlr1'.
    'hj/gBvA5XziAM4fv9Be+D2D4wfcBDN4GMHwBIACnPy+AbwZp+GEXgesyt2r/4vkP'.
    '3rBdz70G/xSKawAEgAAQQDFp3zQEYAMgAASAABAAAkAACAABIAAEgAAQAAJAAAgA'.
    'AeTx+HhsAASAAPgk8beHArABEAACQAAIAAEgAASAABAAAkAACAABIAAEgAAQAAJA'.
    'AAgAATA0j4zZVX0ekQAOROBhVAAAAAAADOwJaOlruhP/4e0AAAAASUVORK5CYII=';

  $cifry[1][2] = 
    'iVBORw0KGgoAAAANSUhEUgAAAIAAAACACAYAAADDPmHLAAAAAXNSR0IArs4c6QAA'.
    'AAZiS0dEAP8A/wD/oL2nkwAAAAlwSFlzAAALEwAACxMBAJqcGAAAAAd0SU1FB9oK'.
    'EQgWKhRUkAsAAAAdaVRYdENvbW1lbnQAAAAAAERtaXRyeSBZLiBLYXphcm92ddNE'.
    'LgAAAe5JREFUeNrt3TFOA0EQBVGPxTW4Axn3D8m4AweBFDkwEoKd9tarEInEv/pP'.
    'z9qYywUAAAA5lpfgeJ5f3z9vf/bx9rIIEAx+twQEGBL+LgkIMCj8HRJcxdOGAATA'.
    'pPonAAhg+gmQxTUwPP07HgRpgGj1EyBe/Y6AePVrgHj1E0D4BCif/QQw/QSoh+8W'.
    'sDn83fVPgI1TPyF8R0Cw8m95EmkzeEfAxvCn1L8GiE69BtgY/qTptwSGJ58AsAOU'.
    'Jl0DCN8SuDv8aQugHQAEONM0E2Bw+FOFIYAjANXpzwrw20C+/95ZrpFXk9wNP/sc'.
    '4MjnCNNvC3YASyAIgH+pfwK4Mi4CgADqnwDqnwAggPongPongOkfhPcC/jj8R/uk'.
    'kAawBKKMIyBc/xoguvkTIL75E8D0EwAEyNc/AeL1TwAQoFz/BIjXPwFAAAIge/4T'.
    'IH7+EwAEIACy9U8AEIAAyF4BCQACEADpbw5fwj//R781gPs/AUAA008AEAAEAAFA'.
    'ABAABCAACAACgAAgAAhQwr+Q1wAawEtAAPVPANzjrB8GIQAIQABk658AeLw/DJmy'.
    'vZ+lGZbw2xIs4bclsAO4BYAAIAAIAM8B3ARcA0mQCf8hBThKoLO/B2AHAAEQ/Zaw'.
    'n46BSv1nBbgnQSl8AAAAAECOL9MJxR6u8GDmAAAAAElFTkSuQmCC';

  $cifry[1][3] = 
    'iVBORw0KGgoAAAANSUhEUgAAAIAAAACACAYAAADDPmHLAAAAAXNSR0IArs4c6QAA'.
    'AAZiS0dEAP8A/wD/oL2nkwAAAAlwSFlzAAALEwAACxMBAJqcGAAAAAd0SU1FB9oK'.
    'EQgWI22IKK8AAAAdaVRYdENvbW1lbnQAAAAAAERtaXRyeSBZLiBLYXphcm92ddNE'.
    'LgAAAchJREFUeNrt3LGtwkAQRVGMaIMenNF/SEYPFAIpSAjZZrE9+84Nf0Dw5+yw'.
    'JvDhIEmSJEmSpIgG/4J5nS+3x6e/36/jAEDo8CsjAKDR8KsiOBptu+FXDIDwAABA'.
    'ACjy+x8AAQCAYtc/AAIAAMWufwAEAACKXf8AhA8fAAEAgABQ5vc/AAIAAAEgAFwA'.
    'Ay+AAAgAABS7/gEQAABY/w8ABIDTD4DhAyAAnH4A9F7VN4ABYPgACACnHwAXQAAE'.
    'gNNvAwgAF8BIANZ/MIC5w085/b4CwocfAcDqDwZg9dsAhp8KwOoPBmD12wBKBWD1'.
    'BwNYMvzk9d8VAMO3AZQKwOlf3inxwmf4HWwAt/1gAL8M3+kPvgQavqcApQJw+oMB'.
    'GH4wAMPvEMCUod6v42D4HW+Ab8M1+OmV/iXQoN0BBIAAEAACQAAIAAEgAASAABAA'.
    'AkAACAABIAAEgAAQAAJAAAgAAASAABAAAkAACAABIACUkBcsTKzVm0n39lILAFYG'.
    'sDcMAKw89L0hAGDD4e8BAQAbD39rBJ4CPAYKAAEgABSYp4A/PAm83uinfI7HwI4Q'.
    'fBvmp8/zQ1BnGKq9wBqAhggqvr0cgEYIqr66/glEcaVy3vj5kAAAAABJRU5ErkJg'.
    'gg==';

  $cifry[1][4] = 
    'iVBORw0KGgoAAAANSUhEUgAAAIAAAACACAYAAADDPmHLAAAAAXNSR0IArs4c6QAA'.
    'AAZiS0dEAP8A/wD/oL2nkwAAAAlwSFlzAAALEwAACxMBAJqcGAAAAAd0SU1FB9oK'.
    'EQgWBb+FrVIAAAAdaVRYdENvbW1lbnQAAAAAAERtaXRyeSBZLiBLYXphcm92ddNE'.
    'LgAAAkVJREFUeNrt3T1OAzEURWHbYhvsgY79l3TsgYVASQXKzCj2G9/v1CGKdM+7'.
    '/kGZ9IZpvL5/fh95/dfHW3/2Z+piyQ2/tdaGaOoxK3wCFJ3+mVgCQqtfA4AAydVv'.
    'CSgY/uz61wAgQGr1WwKKhb+i/jUACJBa/QQAAaqwav0nQHj9EyA8fAKAAMnT35qL'.
    'oOXhr9wAagAQILX6LQEFwl9d/xoABEitfgIInwArqbD+EyB8+gkAAiTXPwHC6781'.
    'F0HTw680/RoABEitfkvAgvCr1b8GAAFSq58AwWd/Aph+AoAAcAycU/9V138NELz2'.
    'E2DTiSZAsemvLksX/nOrv7oAGiC4/gkAAiRPf7wAZ9b/I39zB2GG8HOn3xIQHn6s'.
    'AGePfr4a5ty/XWtYAoLrP06AK9Pvu4Gmf8vWIMADIe78r+IYAWz+ggVw6aMByjcH'.
    'ATaY/js2hwYIZ5h+AiC0/gkQuvOPEED9Bwvg3B8sgPCDBVD7wQLc5efaq9GFf/19'.
    '7yzSSAwf4fcAqv+Xl7TJF/4GDaD2gwW4Ev6R6U+RbAhfA9j0BW8oR8L022PcXADV'.
    'rwGE7x5A8BpgQvi7PQEkSgCTrwGwowCPTLbp37wB/gt4Rfi7CGdqgjeA9gAgQPpp'.
    'gwAaAAQAAUAAEMAdAAFAABAAf+BHo6ABQAAQwBGQACAACOAISAAQAAQAAUAAEAAE'.
    'AAFuQfoj5zRAON2kP5/KN4hd+NkSdOFnS2AP4BQAAoAAIADcAzgJOAaSICb8KAHO'.
    'CrH78weH8LP5ASZGE2qauHdoAAAAAElFTkSuQmCC';

  $cifry[1][5] = 
    'iVBORw0KGgoAAAANSUhEUgAAAIAAAACACAYAAADDPmHLAAAAAXNSR0IArs4c6QAA'.
    'AAZiS0dEAP8A/wD/oL2nkwAAAAlwSFlzAAALEwAACxMBAJqcGAAAAAd0SU1FB9oK'.
    'EQgUKb9rozMAAAAdaVRYdENvbW1lbnQAAAAAAERtaXRyeSBZLiBLYXphcm92ddNE'.
    'LgAAAatJREFUeNrt3T2ugkAUhmGGuA33QOf+Szr3wEK0sjIh4Tccvudtb+d5ZuAO'.
    'BrtOkiRJcTUfwXzP1/sz9/dpHBoAocO/AwIANg6/OoLeqLMDAAABIAAEgFb+BwCA'.
    'ALD6ARAAAkAACAABIAAEgAAoU9ohEADhwwdAAAAgAFz/M6//AAiA5NUPgAAAQAC4'.
    '/gMgAKx+AASA1Q+AALD6ATB8AAwfAMMHQABY/QAIAKsfAMMHIGD41V8AbQdQLgCr'.
    '3w6gBT3c7OWu/vI7gDv9YAB7Dz9x9bsHCB8+AOHDjweQPnw7gHIBWP12AKUCsPpv'.
    'AGDtEA3/RjvAkmFO49AM/7/yzwIM1T2AABAAAkAACAABIAAEgAAQAAJAAAgAASAA'.
    'BIAAEAACQAAA4CMAQAAIAAEgAASAABAAAkALqvrWUgDsAAJAAAgAAaBfKS+fAsAO'.
    'oOS8Y2+mIw53rnZpaYZ8/gnelRA0g8++yewN302g4QMgAASAABAASjoHACB4+F13'.
    'g5+MMfhtRZ4EnnkWcPXHyo6CwwYOwEEIqn6BxOPgHXBU/vYQABsh+N1CSZIklesL'.
    'rY+DlwkDt9EAAAAASUVORK5CYII=';

  $cifry[1][6] = 
    'iVBORw0KGgoAAAANSUhEUgAAAIAAAACACAYAAADDPmHLAAAAAXNSR0IArs4c6QAA'.
    'AAZiS0dEAP8A/wD/oL2nkwAAAAlwSFlzAAALEwAACxMBAJqcGAAAAAd0SU1FB9oK'.
    'EQgUIii5ersAAAAdaVRYdENvbW1lbnQAAAAAAERtaXRyeSBZLiBLYXphcm92ddNE'.
    'LgAAAe9JREFUeNrt3TtSxDAUBVGJ8jbYgzP2HzpjD14IBJMQMQXY4kn3dEoVwXTr'.
    'SbL5tAYAAAAAAAAAWJzuI/gbr2/vH999/Tz2LoBQ+TNEIICb5VeP4IXKbAQgAAgA'.
    'AoAAcNMNQAAQgNUvAPIFQL4AyBcA+XXwMmiA+MpvA02AwFVvAgyUX/0HQkyAcARw'.
    'I9VXvwCC934BkC+AdPkCCJffWmsb8W4B5AuAfAGQLwAIwOoXAPmugcSbAOSbAKR/'.
    '4Tz2vmJUG/nPxZsAgfJXF7/MGcBeHzoB7t7vBRC64pPkTxUA8cFnAPKDAyA/OADy'.
    'w84Ao650xBcMYIR84osGYNQ7A5AvAPIFQH72LYB4ARBvCyBfAORnBfBbieQvNAF+'.
    'IvM89k7+godAUp0BIAAIAAKAACAACAACgAAgAAgAAoAAIAAIAAKAACAACAACgAAg'.
    'AAgAAoAAIIBrWfWPUgvABIAAIABcyyy/6SwAEwDJbD6C/7kCPvueo7YQE6BoUKOe'.
    'Owig8DQZEYEtYNANoOqTRAFMdG6wBZAvAAgAAoAA7OeX4E+zFhY+4mmgAIqu9FGP'.
    'gjvx9Ub8yFfJnfxc+dEBkP/Ao+Aw4QIg3nMA8k2AONEmAPkCgAAgAAgAAoAAkByA'.
    'f1P7wOvgi18KzRaWVXBhIDNOlU/+r9BCz+lH2QAAAABJRU5ErkJggg==';

  $cifry[1][7] = 
    'iVBORw0KGgoAAAANSUhEUgAAAIAAAACACAYAAADDPmHLAAAAAXNSR0IArs4c6QAA'.
    'AAZiS0dEAP8A/wD/oL2nkwAAAAlwSFlzAAALEwAACxMBAJqcGAAAAAd0SU1FB9oK'.
    'EQgVFhAWv08AAAAdaVRYdENvbW1lbnQAAAAAAERtaXRyeSBZLiBLYXphcm92ddNE'.
    'LgAAAfVJREFUeNrt3DFSw0AQRFHtFtfgDs64f+iMO/ggEJFBYaO10Ey/Hzvy/O7Z'.
    'lVXeNgAAAAAAAEQwfAU/8/r2/vHbZ27XyyBA6PA7SECAncOvLsE07mwIsCD9BDB8'.
    'AoAA0k8AEED6CWD4BAABpJ8AOD1D+vdT+ccgDWAFSD8BDN8ZwPDz9n9UA0h+sACG'.
    'H7wCnjn86vXfvgEkP1gAww8WwPCDzwBHDb/D/o+6BiKgAaQ/uAHs/eAGOHL4ndLf'.
    'ogEkP1gAww8WwPA1wOF02/9lBZB+DSD9qQJIvwaQ5FQB9qb/dr0MDRLaAHuS37k1'.
    'ZkL6vwYo/UUF+M/BdT8zzO7Dd+grLIDK1gDL6ptMxQQ4w8AS1seUfg3gzk+AvsP/'.
    'a/pTJJqSrwHaYvcHCSD9GmBZ+pNk8kaQBpD+5FVyWgEeGYT937QB7hnsyuEnivTi'.
    'dK8BECxaOwE8/HkMfxARvmasAGcAEAAEcPonANwCsm4AHjJpAGeA5Ps/NIAzQGr6'.
    '7X8NgM0fRRLAV0CAOOx/DYDqtwCnfw2A1AaQfg0AAoAAyDsD2P8aAASQfgKAACAA'.
    'CGD/EwAEQIAAXgHTAEgVQPo1AJ7A6Jx+V0ANgOoC2P0aAASw/wkAAkg/AQyfACAA'.
    'CIA1tHoUbP83FeAeCQwfAAAAAAAAAIBv+QRd1dmbAT93ywAAAABJRU5ErkJggg==';

  $cifry[1][8] = 
    'iVBORw0KGgoAAAANSUhEUgAAAIAAAACACAYAAADDPmHLAAAAAXNSR0IArs4c6QAA'.
    'AAZiS0dEAP8A/wD/oL2nkwAAAAlwSFlzAAALEwAACxMBAJqcGAAAAAd0SU1FB9oK'.
    'EQgUG3e88rMAAAAdaVRYdENvbW1lbnQAAAAAAERtaXRyeSBZLiBLYXphcm92ddNE'.
    'LgAAAadJREFUeNrt3TuOwjAARVE7yjbYAx37L+nYQxYCPRJfYWznndNON+/aRJmR'.
    'KAUAAABIUf0Kvnc4Xa7Pfr6dj1UAoePPEoEAGg0/SwSLSduOPzoBBI8vgPDxSyll'.
    'NW/m8AIIH14AoYN7BjC+G8Dw4TeA8YMDML4bgNQAnH43AKkBOP3BARg/OADjBwdg'.
    '/NdWwz92/588ewxqNbwbwPBvnH4BBJ34X44/ekiL8TNP/tQBzPJZP0NMi/FzT/9U'.
    'zwCtT33i+FME8I/rPnX8oQMwfPAzgPGDAzC+jwCjp94AXu64AQzvIdD4kTdAq7/h'.
    'M3gAhg/+CDB+cADGdwMYXwDGFwACcPoFYHwBIACnPyeAT8c0/g5vgHdG3c7Havx2'.
    'uv8twLieARAAAkAACAABIAAEgAAQAAJAAAgAASAABIAAEAACQAAIAAEgAASAABAA'.
    'AkAACAABIAAEgAAQAAJAAAgAASAABIAAEAACEAACQAAIAAEgAARADl/XUn73Rdbf'.
    '6vm1OdX4fcfvHUE1/jh6ROAZwEMgAkAACAABIACieBHkRRDJr4IBAACAHDcP+6E/'.
    'Cvjr2QAAAABJRU5ErkJggg==';

  $cifry[2][0] = 
    'iVBORw0KGgoAAAANSUhEUgAAAIAAAACACAYAAADDPmHLAAAAAXNSR0IArs4c6QAA'.
    'AAZiS0dEAP8A/wD/oL2nkwAAAAlwSFlzAAALEwAACxMBAJqcGAAAAAd0SU1FB9oK'.
    'EQgUFZAE37QAAAAdaVRYdENvbW1lbnQAAAAAAERtaXRyeSBZLiBLYXphcm92ddNE'.
    'LgAAAhlJREFUeNrt3dFRw0AMRVHLkzbogT/6/+SPHigEOmBIxmvLeuc2QLLvrqQ1'.
    'trNtAAAAAAAghZr6xd4+vn5W/43vz/ciQGj4U0QooWfLUILPlqCEny1BCT9bghJ+'.
    'tgS78LPZLUG2sGUxs1vBIyX8IwKY2H4qQYAVu++Vz9OxCtTU8M9Y7Gc/V0cBduFn'.
    'U8LPrgL7pPBN9+HXAZT+IQLo+yqAykMAuz9SgAlnawLY+bc53ZgBVAAQQHkkgOEv'.
    '8yRyyxZg8jcDgAAYI4DhTwXQ/wkg/EgBlH8VAKkCPLP7lX8VAATAGAEMfz24xbOB'.
    'Xfv/BIm1gPAKRoDw9tVegG7lf9rs8rCfzw3es4HCb0V1XtgOCzY5/MsF+GuBr16w'.
    'I3t958vYrq8Hh0+AxdO9N4UGn+W9Kzgw9LuFHyXAyvD9YIRSvxFA+ARI6vVTblEr'.
    '4WcGP04AwQcLIPxgAY4OP+32c78aFhz+bQWY/i9aAgifAMI/h6h7AgUfKIDQh7QA'.
    'r5Bfw27nqwDjdr/wB1UATxFrAUhtAQY/FUD4BBB+pACGv+AZwLEvuALY+VoACKD8'.
    'EwAEsPsJIHwCgAAgAAgAAoAAIAAIAAKAACAACAACgAAgAAgAAoAA+Dftbqvq9FxA'.
    'wm1nBAiXggDhIhAgXBpDYLjcBAiXgACuA+iTBCABAUiQiUW+YEDrJDcBTpaiW2Uj'.
    'wIkSaGsAAAAAAAC4jF+53CREFXypZgAAAABJRU5ErkJggg==';

  $cifry[2][1] = 
    'iVBORw0KGgoAAAANSUhEUgAAAIAAAACACAYAAADDPmHLAAAAAXNSR0IArs4c6QAA'.
    'AAZiS0dEAP8A/wD/oL2nkwAAAAlwSFlzAAALEwAACxMBAJqcGAAAAAd0SU1FB9oK'.
    'EQgUNjJjrsYAAAAdaVRYdENvbW1lbnQAAAAAAERtaXRyeSBZLiBLYXphcm92ddNE'.
    'LgAAAo1JREFUeNrt3M11wjAQRWHk4zbSA7v0v2SXHigkaSBw+DHSSO97DWB0r0Zj'.
    'WfbpJCIiIiIiIiIiIiIiIiIiC6Yl/Mmv75/fqtd2vZwbAULhVxChAZ8tQQM/W4IG'.
    'fLYEDfxsCXbQs7OBTwDwCQB+aloq/FEbL8/8hx7XuKfBH731Wi17AnzQJ+4BkuH3'.
    '6Hf2Vc026ydvAt+xvzr8So3gBr4eAHwCgJ/an2wrWGzmLyDAq7Mf/AUEAF8FAJ8A'.
    '4EcK8Gz5B//Y7LOAl4UqgKYvWAAzXwUQAij/BACfAOATIBJ+pUZ4qwj1ejk3M3/h'.
    'CnAPLvB9Y7CLl//IM4GiApj9nZZDFSCw8ycA+ASonl53QwQInv0EEHcBFWe/z8SB'.
    '3y07VFlNHwHCmz5LgLJPgEqzfvTTT7eBYSVfDwC8JaAK/AqHX1SAwFmvAhSAXuXo'.
    'GwGC4ROgI/yqh10JEAyfAB+GP8MRdwIEwydAWLknwIc7/BnfamrgZ4KPFCBxjScA'.
    '+NkCAH8/O+jZaeDnzv5lBPj0jF/5oxUN+Ezw0wug1AcLAH6oAMAvJkCF27X0r5K1'.
    'RPA+RddRgEqbMsB3FqAKfOAHCAD+HFnyWQDogytAtYcxhAgXgAi3E/V6uMfE4RVA'.
    'NQivAKqBCqAaqACqAQFIMNcS8F+JdhooRIBHQTgrsKAArw6+9/4mF+CowfYO4CAB'.
    '3hn8owe5mgS3rmeUXGXOA/QYgOpL0wgJ4jZAjpDgFVCP/m5vCeL2AY4Y4JX2DCI3'.
    'gkgQvASM6Amq9UIE6HzrSoBwESoL4GvhRZcVFSAQ4oh9AI+Di4BwG0gCApBADxDd'.
    'F+gBVAMVILkaeBoYLMGS5wGIUH95IcBgKbyiJiIiIiPyB7GypMhjc+aXAAAAAElF'.
    'TkSuQmCC';

  $cifry[2][2] = 
    'iVBORw0KGgoAAAANSUhEUgAAAIAAAACACAYAAADDPmHLAAAAAXNSR0IArs4c6QAA'.
    'AAZiS0dEAP8A/wD/oL2nkwAAAAlwSFlzAAALEwAACxMBAJqcGAAAAAd0SU1FB9oK'.
    'EQgUBY2zz9AAAAAdaVRYdENvbW1lbnQAAAAAAERtaXRyeSBZLiBLYXphcm92ddNE'.
    'LgAAAhVJREFUeNrt3btxAzEMRdHFjtpQD87cf+jMPbgQOXHsscZLkeA7twLOvksA'.
    '+z8ORFOrLuz+/vlYYR1fH29FgNDwEyQowWdLUMLPFqGEny1BCT9bgtOJUJa4BAAB'.
    'VAECkOCHW6eDN3rw2qWvb1kBXjF1737Zd6nTwNV2f7f1RVWAGQc3pRqcws+eH5wF'.
    'uA6A5DZAABUAyVWAACoACAACgAAgAAgAAoAAIAAIAAKAACAACAACgACX8ZcHLRKf'.
    '04+qAL8FLPzXcJu9AEGbAUAAEAAEAAFAABAABAABQAAQAASYye6fjiNAcPjHscDd'.
    'QMGrAMKfiHvxFwXf9bmGEvz/d3znh1pK8Lnhx84AiV8FVwEGBL/D84wl/NzwIwQY'.
    'Ue79Ns6uNwMInwDbB5/w0koJPTP4bQQQfrAAwg8WQK8PE2DU5dv0t5Nj/8jltfQG'.
    'Atj1wQIIP1CAkbdpBb+wAKPvzwt/YQGU+mABhB8qgF4fKIA+HyqA3R4sgB4fLIDw'.
    'e9Dm5VDBBwog9PGcwjcDLNX/BR9cAYQfOgMIfqMW8Gz5F35wCxC+GQAEQKQAyr8K'.
    'AAKAACAACIA0AXyrTwUggRaA2dRKu9rFoQ0EuKq0kyFcADI0FmDkgEeGJgIkT/md'.
    'JHUWEC5+ORjZleB0ELI57QQCKIfOAgxKqdIvs8AdhSBAsBRdWt7yi+wogXkHAAAA'.
    'AAAAAAAAwHy+AbbRAZYvRQt1AAAAAElFTkSuQmCC';

  $cifry[2][3] = 
    'iVBORw0KGgoAAAANSUhEUgAAAIAAAACACAYAAADDPmHLAAAAAXNSR0IArs4c6QAA'.
    'AAZiS0dEAP8A/wD/oL2nkwAAAAlwSFlzAAALEwAACxMBAJqcGAAAAAd0SU1FB9oK'.
    'EQgUD21mJs4AAAAdaVRYdENvbW1lbnQAAAAAAERtaXRyeSBZLiBLYXphcm92ddNE'.
    'LgAAAuBJREFUeNrt3TF63DAMhFFzP10jd3CX+5fucgcfJKncpEisXYkEMW8usEvM'.
    'TwCkJPLtjYiIiIjiNFIH/uPnr9/V/+Pnx/sAQJjpsyEYDM+G4GB6th7MBwDzrQIY'.
    'n9oHPJivBDBfCWB+ahk4mH//WrsyzMdbsFaZXklH2uxn+qY9wCvmVzf9u2OL7QGe'.
    'Nd9sb7AMZH4wAMwPBoD5wQAwXwZgfioAz8x+5jcBwAMeGUDqTwVA6pcBmA8Amq0l'.
    'zwJebfbM/o0zAPODAbDMkwEIANI/AAgAZj8AmJ8GwDNGMr9ZBviuoZ8f74P592vJ'.
    'TiBj9QAEAAIAASBdq5+PAEAGIABQeTkkSv2XAQgABADp/0rZk9/A/DufncgAoTMf'.
    'AOHLPwCY/QBINx8A4enfKqDw7J/11pQMEDrzARBe+5WA8NQPAOYDIN18PQABIK3p'.
    'AwABIHndrwnU/AGggvlVPpBVAvQAlNb5KwHhdV8GIBnA7AdAvPlKQGDTB4BFqnow'.
    '1ugwE2YGt9vVNmN382cFuetllmN342cEu/NNpqOL+XcE/pXx7nIY5qOb+Vf915Tb'.
    'TUb3YJydiVeNtV0GSFizp5kfkQH+Z8rVY9vtIOwYAP42544x7XgKehQAXyalz/rb'.
    'AJgRiLQNKE1g0YB3udFky1XA6uB3uvFk62XgChC6XXfTYh9ghildL7FqsxF0pzmd'.
    'L7m6FIDVHbrbyIrsA8w2YiZ43SA7dvzTKzPN1293AeFgejYI4w4Dng3KrlvO7beC'.
    'zxp0JiAdHzQpASGmdygJj9lBqvQq+R1m7Qb3tBJQITD/Mjz1/YDRme6zZiRC0B6A'.
    'Vwy4crwtPg1L+y4gAYI2AFT9NKw6CNsCsCKIHXuE0t8GVq2bnZ56ltuy3WUjpQsE'.
    'Jc4H2HUbtQMEY2UQujxSXQVCCQDOBqDzWzuzQSgDAK2B4QoAHBIVLgCEr2aUgE1L'.
    'QYllIK2BwuvvRERERERERER0Wn8AglLLxhC0yfkAAAAASUVORK5CYII=';

  $cifry[2][4] = 
    'iVBORw0KGgoAAAANSUhEUgAAAIAAAACACAYAAADDPmHLAAAAAXNSR0IArs4c6QAA'.
    'AAZiS0dEAP8A/wD/oL2nkwAAAAlwSFlzAAALEwAACxMBAJqcGAAAAAd0SU1FB9oK'.
    'EQgTOnSUdCoAAAAdaVRYdENvbW1lbnQAAAAAAERtaXRyeSBZLiBLYXphcm92ddNE'.
    'LgAAAlhJREFUeNrt3UFywjAQRFHGxTVyB3a5/5Jd7sBBkhukSLCNZvr1noJy/2mN'.
    'ZElcLhStSn8AH59f3698/nG/FQDCTJ8EQTE8G4JidjYIxfhsCIr52RAU87NBKOZn'.
    'Q7BdKBrUzUPN1pX5z8f1ROiK+X8bo1/5/hV7gUo0fw8j/vtbVoOg5RCwwgN/3G81'.
    'YUioydV/VrX99betlAKmgeEaC0D39/QAaBSxnWHbVH42BNu06ic9AKUC0CWGV0q1'.
    'zYOSAKo/uBHcVL8EIACIfwAQAFQ/ADSAACAAEADEPwCWN18DaAggAFAkAOJfAlAq'.
    'AKpfAhAACAAEAAIAAYAAQAAgALxVHV9lLwXAMyt8q64Cdt3HsFwC/GYw8/fXkpdE'.
    'WevXA4yWS6JIAuj8ASD+AcB8AIh/AKh+ABAAxP87ZMXtBPP9aRRJANUvAYz7AGA+'.
    'AMz99QAJ1d9lT8OVvXmxLwEONr3TjiYABJsPgHDzzQLCOn5NYGCTZwg4wfzO29iL'.
    '+bnmxwGwd+RPOMBSzM80PgKAI5q8acfWivGZxo8FgPnBADA/FICjFnQSjqkX0/NM'.
    'bw3Akcu3iRdTFPOzbyQp5mdfR1Op5ruHqAkAlnCPVcyGEOY3TIDUbVoAYL4hgPnB'.
    'CTD5JI4EUO0AIEPAbvGv+oMTgPnBADBfAhAACAAEAAIAAYAAQDkAuNxhH9UUYy0M'.
    'hQMADgAAYQIAU8b3LrABIByE0ZtCwdAYgOlTvVVgAEA4FFFHw0DQEAAQAEB/AIAs'.
    'QAAQDgIAwkEDQDgMAAiGwTQwGARvK4mIiOgs/QAXzk/DHVvQnAAAAABJRU5ErkJg'.
    'gg==';

  $cifry[2][5] = 
    'iVBORw0KGgoAAAANSUhEUgAAAIAAAACACAYAAADDPmHLAAAAAXNSR0IArs4c6QAA'.
    'AAZiS0dEAP8A/wD/oL2nkwAAAAlwSFlzAAALEwAACxMBAJqcGAAAAAd0SU1FB9oK'.
    'EQgTNJMsWS0AAAAdaVRYdENvbW1lbnQAAAAAAERtaXRyeSBZLiBLYXphcm92ddNE'.
    'LgAAAfNJREFUeNrt3MtRw0AUBVENpTTIgR35L70jBwKBFOyyRjVSnw6AAm6/O08f'.
    'e9sAAAAAtBjlP/7z++dv1s/+fXwNAoQCv6oEQ/BtCYbg2xIMwbclGIJvSzCE35Zg'.
    'F3ybXfAEEDwBBF9lCL+9CO7Cfy+gq8u5C/86D25SApwRfjn4pQWYHb7gF14Cr/iM'.
    '/tXf2RJ4cvgm/gb3AQRPAMHfWYAj6l/wr/MhfAIInwAggIWPAMInAAhg+gkg/FOv'.
    'Yghg8gkAAqAowCuVvlr93+Fl1SUa4Jlgnf1z8E89efpXE9kOYAdA8ewnAAiw8hUP'.
    'AYRPAOc/AUAA9U8AEMD5TwBMwLOAydO/+kMsDWAHAAFgB0Dv/NcA0ADl6dcAk8K3'.
    'BIIApp8AWa70CjsBwuETAAQon/8EiNf/trkRdNj0X/WzixrADgACgABoLX8EiF/+'.
    'EcD0E6A+/QQAAcrTT4D4+U8AeBZQu/evAaABTL8GAAGQFqB87Z/fAd4J/27fWu4I'.
    '0ACmvzr9GgCtBjD9GgDVBjD9GgDVBjD9GgAEMP0EQG8H8MxfA4AApp8A6AngpQ8N'.
    'gOpVgO1fA6AqgLNfA4AAzv/sEnj3I+BIUQkQF8IREBeeAHEJCOAqAAQAAWzFBAAB'.
    'tAABSJDCJ4PispuQG4ir6QAAAAAAT/IPNAPuNUtdR18AAAAASUVORK5CYII=';

  $cifry[2][6] = 
    'iVBORw0KGgoAAAANSUhEUgAAAIAAAACACAYAAADDPmHLAAAAAXNSR0IArs4c6QAA'.
    'AAZiS0dEAP8A/wD/oL2nkwAAAAlwSFlzAAALEwAACxMBAJqcGAAAAAd0SU1FB9oK'.
    'EQgXDahFFCEAAAAdaVRYdENvbW1lbnQAAAAAAERtaXRyeSBZLiBLYXphcm92ddNE'.
    'LgAAAjhJREFUeNrt3cFRwzAYROFYkzbogRv9H7nRA4VACZBYJEjv2wIyyezb1S/Z'.
    'ji8XIiIiKurY8Ue9vH18zf7Mz/fXAwABo2sgHMxvg3Awvg3Bwfg2BIP5bQ3mA4D5'.
    'zgGYX50DhgxoAOkPt8BgflvX2g/+Kak1AI8d03+2ju/9HisuA8dO5s8y4Mx3WQ2C'.
    'wfy2ttgGzja/BNNYPf2SrwHs61cFQPo1gBaoAiD9GoD5VQBKBy0AIABIPwCmDH4U'.
    'XgKkf3EAVL8GoCoA0q8BCADSDwACgPQDgAAg/QCgDgC3JFr6N22A3xjL/Mfroc8G'.
    'MtgMQAAgABAAVlDhDiYAaACqph8ABIBy+gFAFydzE9O/4kmnBjAD0Az5p1DDnxnA'.
    '2q8BJB8A1n4ASD8ACADSDwDrPwCYDwDm/19lD4JKbwXRAIY/DTDD/B0fbBnMtw2k'.
    'aPpTAEh/eAbwboJwA0h+uAG8jzAMAPPDS4DaNwPY9lWXANUfBoD5t+vKeNtA5kfT'.
    'vzwAzLcLYH61AWalv/7nlaNsPi3YANb9cANIfrgBZpsv/YsA8BepZ/4iAEh9GADD'.
    'niGQ+dUGcMgTbgBbvWgDGPjCADA/DIBp3wzAfAAwHwDUmwHcxQuAp+75wfNEAHY6'.
    '8FkZJADEQQBAHAgAxEEAQBwIAMRBAEAcBADEgUgfBAFhs8vB4FgQACA8F4LMWfiu'.
    'gJ2FwJW0xWECQBwEAMRBAEAcBADEgQBAHAQAxEEAQBgKB0FhINzQSkREJ/UNG9tc'.
    'R9DMp+sAAAAASUVORK5CYII=';

  $cifry[2][7] = 
    'iVBORw0KGgoAAAANSUhEUgAAAIAAAACACAYAAADDPmHLAAAAAXNSR0IArs4c6QAA'.
    'AAZiS0dEAP8A/wD/oL2nkwAAAAlwSFlzAAALEwAACxMBAJqcGAAAAAd0SU1FB9oK'.
    'EQgTLfdH8e0AAAAdaVRYdENvbW1lbnQAAAAAAERtaXRyeSBZLiBLYXphcm92ddNE'.
    'LgAAAntJREFUeNrt3Tty20AURFEOitvQHpxp/6Ez7cELkSNHCiwVQXBm7ulcVSL6'.
    'dr83AD+3GxERERU1ai/47f3j85G///P71wBAyPDdIRiMb0NwZ7wdgPnhFhjMb0Nw'.
    'MN8IYHy4BQbz2xAM5rch2GIHoBAAFr5zdWf+1/ouQXavGr/bQ50tATjbeKYvtAO8'.
    'soZLoBwV839qagWCxDFQ9S8EgLmvAZhfBeDM9DN/MQCYHwaA+WEAmP9a3Xcwn/EL'.
    'AnCG+YxfdAQw3w7A/CoA3tChAaS/CoD0awDprwIg/RpA+gHAfAAwHwDMB8D0qiyt'.
    'h/RrAAofWQGgAaiafgAQAOpLKwA0AD06/1c+sgJAA1Bx9gOArgXgJ0l5Raqqb1i5'.
    'tAG+Y6xnANfKxX6gAXaA1Q5wa79fEQDR7R8ABIB6+gFw83kFDWAESD8AKDn/0wBI'.
    'vwagKgDSHwaA+RrAAlgFQPq/ajC/m/4MAMw3AqjaANKvAajaANKvAZhfbYBH0u+X'.
    'Q8kOIP0agDSA9GsAWz8AKud+AEg/AKQfAMx3CmgaXxwFg/ltEAbz2xA4BcT3isGY'.
    'dhMAIA7CYH4bAjtAfC84XPy2NEAcRABogI7OWuLe3j8+d2mCJbbasy72PwA8Q1gI'.
    'gLPNf9Y896NR4ZGw8nKYXwLrEBxMPn9krQSCY+CTAFkFBADEGwEAF42JWSE4GNuG'.
    'QAPEIdj6RtCjZj7brBluHmmA/xj0TJNmaAMNMJlZV7eCBpjMmKtb4WBYGwINMNle'.
    'YAlcJKm7gKAB4iAc0tz6P5dsgJlu5+4GwVYfDZvFgDO2+KteS/a7cWYHAQBhEK5s'.
    'MgBMBkL5G8uIiIiI6CL9BStJebGVhy+YAAAAAElFTkSuQmCC';

  $cifry[2][8] = 
    'iVBORw0KGgoAAAANSUhEUgAAAIAAAACACAYAAADDPmHLAAAAAXNSR0IArs4c6QAA'.
    'AAZiS0dEAP8A/wD/oL2nkwAAAAlwSFlzAAALEwAACxMBAJqcGAAAAAd0SU1FB9oK'.
    'EQgUL1YIBgYAAAAdaVRYdENvbW1lbnQAAAAAAERtaXRyeSBZLiBLYXphcm92ddNE'.
    'LgAAAp1JREFUeNrt3bF16zAQRFEsD9twD85+/6Ez9+BC7A7+sSwBXHDuxApEvreL'.
    'BSmRY4iIiIiIiEhQyil4Pm//Pr8f+fzXx3sRIBB8RwkIsBh8NwkIsBB4RwkIsBB2'.
    'RwkK8PXAO0lQwPfJFRIU4NkSFPDZEhTg2RIU8NkSFPDZEpzAZ+cAv3fVzj7WAn8u'.
    '+Fd835nLQIE/H8qz35sADeA/C6HrreMCf91J7yhBgb522/XX44oRYCX4q+7A/eUY'.
    'Z33XM7XiO/0u78qcKfABbyrAbPjA/z/HXQ/s6+O9usLv9L2Ou8JX26ECgB8sAPjB'.
    'AoC/+XUA4AkAeuIS8ChM8G84A/wGaud9/c5xQi9Ip5tBBxz94dsGgk8A8AkgBFD9'.
    'BAjMzO0vAYKrnwDh1U+A8Oofw5XA1vA9HwB8HQB8Q6Chb2JOpzxn4LMENAd/xe8d'.
    'LAGBVa8DNIR/1a+ddIDAwY8A4BNACBA9/BFA+7cLuLL6vTUsFH63P7cQYBH8rv9q'.
    'IkAw/DHcDIqZ9gkAvCXgCvDd/9FMgGD4BAiHP4YrgfHvHvLq2AnZ6UkmBXwu/CgB'.
    'PJE8VADgQwUAPlgA8IMFAD9YABN+qADAhwqg3YcKsOrybcKzib09PBj+dgJo+cEC'.
    'GPRCBQA+VADggwVwrz5UAFUfLAD4wQKAHyyA9T5YgFnwgf99vD1cB7hH9QO/kQDg'.
    'P3Y+vDImGP7sQbl2rf47tPxHz8WMYz5V/P22v22XgDs/asU2EPwtc3YHLxt3AANf'.
    '/6I4wNcBZIPqn1UIh+rXAQx9odXfeglQ/Rt2AK3fEgD+ZqlO1X9n+B3X/5cJYPDb'.
    'tyMW+Lnwn54BwDcECgGEALLtdpgAOoCkVj8BhADJ1T+GC0HR8F8iAAn2hS8iIiK5'.
    '+QEcl6neLIpBTgAAAABJRU5ErkJggg==';

  $cifry[3][0] = 
    'iVBORw0KGgoAAAANSUhEUgAAAIAAAACACAYAAADDPmHLAAAAAXNSR0IArs4c6QAA'.
    'AAZiS0dEAP8A/wD/oL2nkwAAAAlwSFlzAAALEwAACxMBAJqcGAAAAAd0SU1FB9oK'.
    'EQgTJxeSGPMAAAAdaVRYdENvbW1lbnQAAAAAAERtaXRyeSBZLiBLYXphcm92ddNE'.
    'LgAAAgJJREFUeNrt3cttwzAQRVErUBvpIbv0v8wuPbgQZ5VlPgZIajjv3AIMmO9q'.
    'hiNb1O0GAAAAAAji2P0LvL5/PmZ87v3j7SBAWOiJEhxCz5bgFHo2p9BtAoUf3AZO'.
    'wWfzInwCCJ8AMAU0uvr/u2lTgS4SYMbCp9y63V6AEeELu9kYKHgCCD1hCni2/Au/'.
    'kQB23CpAqdJPyOICgACu/tQpYEb5F/omFUD4a1k6Zv0VxMjwR4XefRRd/uV+CmbE'.
    'Qo++0v0rOHhT57kAO3otwDjXXwY3gsKnCRUgvBqoAOHVgADhEmgB4S3BGBgugRtB'.
    '4RL4y9VgkQgQLsJuApgCGs/4KsAF1UALIMFWEmgB4S2BAOEQoOg4SQBtgACqgCnA'.
    'RKACqAQEAAFsBgkAAqgCBCABAUAAEAAEAAFAABAABAABQAAQAAQAAUAAEAAEAAFA'.
    'ABAABBiIgyKFrwIInwDC3xxPB08Ov/rzAwQIDp8A4eHbA4AAM9jpuUEtYHD5d1Ko'.
    '8I2BNn37cIo8++XSh/DzXhJxmQC/LfYVi5ge/lIBKh2wOLLkE6DoxmpFX+9wTlBp'.
    'AZ5d5JWbuS6HRJUXoCKdjokzBoYG/43fAoLDJ0B4+KXHQKE3E2AnCbw4MlSGpOAv'.
    'F6CSDInBlxJglQTJQZcXYJYIQgcAAAAAAAAAAAAAAAAAAAAANOYLs+kDu/FZqncA'.
    'AAAASUVORK5CYII=';

  $cifry[3][1] = 
    'iVBORw0KGgoAAAANSUhEUgAAAIAAAACACAYAAADDPmHLAAAAAXNSR0IArs4c6QAA'.
    'AAZiS0dEAP8A/wD/oL2nkwAAAAlwSFlzAAALEwAACxMBAJqcGAAAAAd0SU1FB9oK'.
    'EQgTIf7xvcYAAAAdaVRYdENvbW1lbnQAAAAAAERtaXRyeSBZLiBLYXphcm92ddNE'.
    'LgAAAkhJREFUeNrt3btxwzAQRVHBwzbUgzP1HzpTDy5Ejhx6RiNTwC7euQ2YwLtY'.
    'fCgTlwsAAAAAAAAiGDs26nq7P1b+/e+vz0GAwOA7yjCEni3BEHa2BEPw2RIM4WdL'.
    'cAg+mw/hE0D4wQzhZ68DDh2eLe+x4+jvdBRLgMDQr7f7o8rzHkI/71k6TgNHlRGh'.
    'xIduA4XvHMBuI1UAoz9YACd+wQIIXwWImF8JcNLoF/4m5wAWfSoAUgWw8AsWwLyv'.
    'AoAARn+kAFb+KkBM+B0Xuh/Cz97lWASGb3EP8eWETQChz58CnpnbK8//u59gjtUd'.
    'WS38GYFXarM994LRTgClvowItoHh6wsChEtAgHAJCBAuAQHCsQsotiibvTMgwAsS'.
    '/BXSWQLNlIAABasKAUgwTQIChEtgF2AXgOQqQIBwCUwB4RBgMtV+AEOAcAkIYApA'.
    'chUggAoAAoAAIAAIAAKAACAACAACgAAgAAgAAoAAIAAIAAKAACAACAACgAAgAAgA'.
    'AuBpqtxDQIDg8C8XXwgpH/67/4lUBQgd+b+4NCo0eFNAg+BnfEPgeHcDU+8C7nLb'.
    '2JjRwDQJzgi/9KdiX23gjiK8Y6SX/1h0J8O7lfcW9wWc2QEdRNj5PsFRoUMqSjBz'.
    'Ebey/aNS56wWIfHyyFGto2Z2yOqtWoXKN7p2Xme2uTuYBP3XO/9+GBL03u2c+lBk'.
    '6LfVfeuDJQrR7YArZsUt9MUC7CTBTu80ljSkowi7vtFc1qgOEiS8xl7ewEoipP54'.
    'BQAAAAAAAAAAAJvyA5zsKBE/LR5PAAAAAElFTkSuQmCC';

  $cifry[3][2] = 
    'iVBORw0KGgoAAAANSUhEUgAAAIAAAACACAYAAADDPmHLAAAAAXNSR0IArs4c6QAA'.
    'AAZiS0dEAP8A/wD/oL2nkwAAAAlwSFlzAAALEwAACxMBAJqcGAAAAAd0SU1FB9oK'.
    'EQgTFd9FSXMAAAAdaVRYdENvbW1lbnQAAAAAAERtaXRyeSBZLiBLYXphcm92ddNE'.
    'LgAAAmVJREFUeNrt3cFxGzEMhWEz4zbSg27p/6hbelAh8cHjGV8SxR5KBPC+vwFb'.
    'wr8PJHa5enkBAAAAAAAAAAAAAADAKJav4J2fv37/eebfu10viwBBBa8qwVLkbAmW'.
    'omdLsBQ/W4SlyNkiLMXPlmApfrYIP0wAssUnQLgEWsATonfX//6IdvDq+np8n71d'.
    'L6vqGiYmASqMXXdIsPtzjN8GVrnpUlWC0YOgasXfJUO0AFb4eyUgQHh7MwcI2YkQ'.
    'QGpoAVqBBCANAawFtACtQAJIAgJAC5jXCr6aHBIgvBUQIBwChKcAASQAJs4ECKD4'.
    'BFD8+3gqOLTwHxgEDSr8d3YLEiDgKpcAAUX/7qyAAA2v2l3FJ0B48QkQXnxzgMbs'.
    'uj9gFxBaeAmg+BIgufAECC66XUDBnYCzga7II0iAQklxQjIJEJ4UEqBQGnhZNAme'.
    'LoEWEN4SCBAOAQiA5DZAgEFzBAKAAFKAANYBBAABQABt4D7Hnwj624Jn6v13CfCp'.
    '8P9a7ab+TmCEAP9bXBIMFEBRJQBhCEACApCAACCAFCAACaIE2DHhuzdEQvEE2DXm'.
    'JUHjFnC7XtauNFDGxmsAEpylzB23aj+rfvrzxf1o1OQkqJxQa+KXVSUJOhwVHxOZ'.
    'FUTYeaV7RUwjCXZH/DPFbfHY1YnXqCcUv40AldKg4hu/IwSoIMGjin9y0dryydtJ'.
    'g5/TO5a2j153l6DKVrX9s/ddRSBAsASVRtZjTt90kaDa/YpRx6+qSdDheNu483dV'.
    'JOhytnHkAcyTEnhVbKAEnU8yjz+C/QgRHF0HAAAAAAAAAAAAAABAWd4AK6pJgURb'.
    'hVAAAAAASUVORK5CYII=';

  $cifry[3][3] = 
    'iVBORw0KGgoAAAANSUhEUgAAAIAAAACACAYAAADDPmHLAAAAAXNSR0IArs4c6QAA'.
    'AAZiS0dEAP8A/wD/oL2nkwAAAAlwSFlzAAALEwAACxMBAJqcGAAAAAd0SU1FB9oK'.
    'EQgTALKYrZgAAAAdaVRYdENvbW1lbnQAAAAAAERtaXRyeSBZLiBLYXphcm92ddNE'.
    'LgAAAd9JREFUeNrt3c1RAzEQBWFLpTTIgRv5H7mRA4FACsbsj2be1wlgV7e0oy3W'.
    '+3gAAAAAAAAAAACgLaPqB3/7+Po5+298f74PAYRJT4tgkJ4dwSA+O4JFfDaTfAGQ'.
    'LwDy3QcgP3IQnORns7p8kVdXZ3qEq/LKT7hV224G+K/8M6Q/+5k6BreqyLfam98H'.
    'IL95AK+u/rPlpw+Bk3w7APkCyJKPjYfAK+S/EmbXKGda8eQHB+C6v3kAZ64088jm'.
    'AZB/D7cPXLse91JOIvPOle24F7ADdBj6PBdg4m8bwiA/O4JBfnYEcXcCq0QmAAjA'.
    'LiAACABOARtvz9VPBHE3gp4R9teAKkfgXvxBO0jVCMwAZgAk7wJ2gKbDnQAgALuA'.
    'AEQggL0HRwFAABAABGAQFAAEAAFAABAABAABQAAQAAQAAUAAEAAEAAFAABDARXT+'.
    'jWEBBMsXQLh8AcDTwUeu/or/PCqAYPkuAbADJK9+AYTLF0C4fDNA0HnfDnCw/C7P'.
    'Cy7inQLID139cQEcseq9MoZ8l4DE63zXH4lYxBsCSQ9d/WUD8EKIIgFU3YqT3mk8'.
    'yM+Vf1oA5DsFEC8A0iswyc9mEW8HID+YtsdAwm8O4OoICAcAAAAAAAAAAAAAAAAA'.
    'AMn8AnZs+xrA7GciAAAAAElFTkSuQmCC';

  $cifry[3][4] = 
    'iVBORw0KGgoAAAANSUhEUgAAAIAAAACACAYAAADDPmHLAAAAAXNSR0IArs4c6QAA'.
    'AAZiS0dEAP8A/wD/oL2nkwAAAAlwSFlzAAALEwAACxMBAJqcGAAAAAd0SU1FB9oK'.
    'EQgTCLxDJaoAAAAdaVRYdENvbW1lbnQAAAAAAERtaXRyeSBZLiBLYXphcm92ddNE'.
    'LgAAAjdJREFUeNrt3UtSAkEQRdGuDrbBHpyx/6Ez9uBCcKLhSIJP01TmO3euEfa7'.
    '5KfAYlkAAAAAAAAAAAAA9GR0+4OOp/Pl2d/x9fkxCBAafpoMQ/jZEgyhZ8swhJ8t'.
    'whB+tgRD+NkSDOFnS7AKP5sh/OwqECHAtXC2lKuiBO0FuCeUxGPkQ9fe9kgQvz+T'.
    'NG8cBJ8twir8bFaPIFsoAoRLQIBwCQgw0cpKgLCHTwBtgACqAAFI8EbaHgVfk8CB'.
    'UbEKsHVgKkTBFkACMwDSBdC7VQASaAEkMAP8SEAEQ+DD1YA8jbaAe8MU/h9tTgKF'.
    '6hwABAABQAAQAAQAATak69vHBAgOf1kafyRM6LcxBL1t0NVOJFfhGwKFTwCkMlSA'.
    '11BlFlABwlsMAcIlIEC4BEM4+zDrTOAgKFwCn6PbURQChMtAABJMJwEB3iDCTBJY'.
    'A5tO9wSweWgBWoEKoBUQQCsggCpAABIQALaAOfv7O6uGChAOAcK3AQKED4ME0AJA'.
    'AMS2AQKoACAACAACgAAgAAiAHKa+Jey/N0lcDR9QAa69Q+Z+n+YC3BIwCcwAIAAI'.
    'gFwBzAEqANIFUAVUgGklcE9gMG4KfQJHvSpAaQkqzSVtWsAMD/14Ol+qDaWjY7B7'.
    'V49nQ/fPoYWrQeXw21aAVz3cjl8wNbyis4fc1cOyBiJY6FKvrA6twGXRoRL4xpBg'.
    'CWaeYUoPV7OLUGF4LT9dzypBlc2lxXo1iwQV19VW+/XeIjifAAAAAAAU4xu1gxs8'.
    'xvrv/QAAAABJRU5ErkJggg==';

  $cifry[3][5] = 
    'iVBORw0KGgoAAAANSUhEUgAAAIAAAACACAYAAADDPmHLAAAAAXNSR0IArs4c6QAA'.
    'AAZiS0dEAP8A/wD/oL2nkwAAAAlwSFlzAAALEwAACxMBAJqcGAAAAAd0SU1FB9oK'.
    'EQgTDlUggJ8AAAAdaVRYdENvbW1lbnQAAAAAAERtaXRyeSBZLiBLYXphcm92ddNE'.
    'LgAAAidJREFUeNrt3cFxwkAQBVEttWmQAzfyP3JzDg4Eh2BUgDQ7/3UCLtO9g7QS'.
    '0rYBAAAAAIAURtI/e73/PI/6W7+P2xBAoPjVQhiEZ0cwiM6OYBKfzSQ+mwv5AiBf'.
    'ABCA1S8A8gVAvtPAPPl7Nmm6hTmT5e/dnes4lWaS/FWu0EUG8Cn535Lc9Zik1T5A'.
    'VfmuBh6wusgPngDGvq+AqDAFYPULwOoXgNWfHEBl+SttOF3Iz5VfZh9gz4dPftMA'.
    'XpFAfvMAqk4dAZDfUn7sPoC7jcIDWOGMRABWv2OA6vI73GE0yM+VHxMA+cEBkB8c'.
    'APnBAdjocRpIfuoEqP4bAwGQL4DqB3wpkQzys0MY5GeH4CygaYwCEIEAIABTQAAQ'.
    'gCkgADTdB6i+2rwxRASeEZQelwBEUDYCB4FNvssFAAGYAgIQgQAgAFNAABAABAAB'.
    'QAAVqHqxSgAmAAQAAUAAEABepct2sABMAAgAAoAAIAAIAAKAAPAPXV48IQATAKmr'.
    'XwAQgAAQO/63zfMBDhPv+QBWvQmQKr7yzSOT5qwV7yuAfAGQ7xjgMPnVbx6dxGe/'.
    'TXwQ/j1WuHXc28OD5bcO4KzRvtoPRrw5NFR8ywCs+tAAiA8NgPjQAM48d+/2qNhB'.
    'eK788gF4L3BwAFZ9cAAV5HcXXzYAR/bBAdjBCw7AVbpziLofgPjQAIgPDYD40ACI'.
    'Dw2A+MAASH8PvwsIZ+mNIKu/WQB7IiAfAN7lD47vO+HfBBrgAAAAAElFTkSuQmCC';

  $cifry[3][6] = 
    'iVBORw0KGgoAAAANSUhEUgAAAIAAAACACAYAAADDPmHLAAAAAXNSR0IArs4c6QAA'.
    'AAZiS0dEAP8A/wD/oL2nkwAAAAlwSFlzAAALEwAACxMBAJqcGAAAAAd0SU1FB9oK'.
    'EQgQNyEIW1QAAAAdaVRYdENvbW1lbnQAAAAAAERtaXRyeSBZLiBLYXphcm92ddNE'.
    'LgAAAidJREFUeNrt3cFxwzAMRFFJkzbcQ27u/+ibe0ghTgmZjEQR4L5fAS18LgFm'.
    'Im0bAAAAAAAAAAAAlmXvuvDH8/3ptuaf1/dOgMDCV5ZgV8RsCQ7Fz+ZQfAKAACAA'.
    'CAACgABIuQMgQHjxt23CTeDou4ARD/mKNVcs/hQBrpbgjgd7dr1Viz9NgG6cEaBy'.
    '8fUAIMDsnoUAun4CKL4mcKn471J8AoQX3xEAAiR2/gSAHmDE7u92/ksASIDk3S8B'.
    'IAGSdz8BLh79OopAgAGzv6vgcAE6iaAJbCYVAUjgCOhaqIpHggQITwMJMKFAlZKA'.
    'AOESECBcAgJMlmK2BASYLAIBSDBVAgIUEIEARJgmAQHCJXATuNhcLwGaJ8HdIhEg'.
    'XAIChEugBwhHAoSngAQIhwDhoyEBwiXQAxTvB0YLJAH0AEg+CgggAbBi70AAmAJM'.
    'A3/zVenHd37Rgh7gAvN9WXRRAf5TWBKYAtwHrCSAHS0BSFM4BRwBEgAEAAH0AQQg'.
    'AQFIQICFRx5MSoCzEjye748kMAUQobMAVx4FJGiaACRwBJBAD3C9BEQIaQKJsJAA'.
    'o+4HSBCaAJ0kqLS+feWHUeUWsvIr5PaEHTFLhA5fIit1T989DTquv+QfajRy9wlw'.
    'JP1YNJoCSGAMJEHCGKgvmLsR2u2wNBFGp2DbiPXfQ+ECrCqB18WHSuCTMUES+HRs'.
    'qAQVx9rl5uyKIrjPAAAAAAAAAAAAAAAAwG38AiQJKC0KkoZzAAAAAElFTkSuQmCC';

  $cifry[3][7] = 
    'iVBORw0KGgoAAAANSUhEUgAAAIAAAACACAYAAADDPmHLAAAAAXNSR0IArs4c6QAA'.
    'AAZiS0dEAP8A/wD/oL2nkwAAAAlwSFlzAAALEwAACxMBAJqcGAAAAAd0SU1FB9oK'.
    'EQgPJ/HlRa4AAAAdaVRYdENvbW1lbnQAAAAAAERtaXRyeSBZLiBLYXphcm92ddNE'.
    'LgAAAlpJREFUeNrt3c1N7DAYRuE4chv0wI7+l+zogUJggVijmWD7c97nFHAlco7/'.
    'oozvcQAAAAAAgmgewQ8vbx9fo/7tz/fXJoBA8TtE0IifS7UQGtnZETTCsyPoxDsF'.
    'kB88C3TSs+nEZ3OSLwDyBUC+PQDhl3fpO0baia97RIsKYMcRL4DN5ScKLxfATPmE'.
    'b7QJJF4AW4nf9Zjaic+VfxwbvgomXwDWegGQbw9A/L9Q4uE9so5WEn51/a/wt2zz'.
    'MCuO9CsBVPl7TJ/B8gUQLv+W7wFgBjD6zQAQQMFjnwAgAKNfAJG4ICJ49LsihnxL'.
    'gGlfADZ+lgBTvwDIFwD5AiDfJtCGTwCOfAKAPYAlYOEMYgYID1AA4REIwB4AydfJ'.
    '+x9Dip7/Z0VgCQgPs3nItRk9E5zkZ3OSLwDyHQPJT90HdPLnyKkan2PgpJF5ZRSP'.
    'jEcAE6dlPw8Plu8UAAEY/e4IghnA2l9pFjjJy9v4xc4Azx7d7nwfcdwSMOvlzS70'.
    'IxA3jNsEQgAQAAQgAAgAAoAAIAAIAAKAACAACABjqPQjEQEEyxcAMj8IMfIFEC/e'.
    'EkC+ANLlWwKCxf/i69gNxI/8ilkAxUf86E/YBRAsXwCF1/lZP14RQMFNncuiiXcK'.
    'SDq+rfytYiN8Lat/qNoIzxR/+wAqv5Gr9PP0Rnqu/KEB/CXjPx7ETu/dq15K0VaP'.
    'xEcezI63fVe/jaStlH9ndrmGxvcAwfKPw/cAkdIFECxcAMQLIFm4AMKFCyBc+PD3'.
    'AJXfBbgidlIAVSIgfGEAK0MgvlAAI0MgGgAAAHiCbzmdRk+rNuQUAAAAAElFTkSu'.
    'QmCC';

  $cifry[3][8] = 
    'iVBORw0KGgoAAAANSUhEUgAAAIAAAACACAYAAADDPmHLAAAAAXNSR0IArs4c6QAA'.
    'AAZiS0dEAP8A/wD/oL2nkwAAAAlwSFlzAAALEwAACxMBAJqcGAAAAAd0SU1FB9oK'.
    'EQgQEx0Lv4UAAAAdaVRYdENvbW1lbnQAAAAAAERtaXRyeSBZLiBLYXphcm92ddNE'.
    'LgAAAlZJREFUeNrt3ctxwzAMRVFT4zbcQ3buf5mde0ghTgOZiS3zAxDnFpCh9C5B'.
    'kLKiywUAAADlaG7B39zuj2fvv/nz/dUIUCjwDCI0ga8higRN4LUlaAKvLUETfG0J'.
    'mvBrS3AI3zmA0AtXgUP4KoDwC1eBq+BrcxV8/xmaSdQm+DEl+ezYZy8BTfjjbnoG'.
    'CQ7hj7vZER//hu8BRoefIRTnAMInwA7hZ9gNXIVeN/ytK4DwCwsg/KJLgOCLCrC6'.
    'u8/8jOIQft3wt98FCP1/WuYbPHv2zwjew6AXb7bwNxegUpn3s/DC67sXQ4SvCazY'.
    '1Xs5VPC2gco9AYS/mOOC0hCAANix2SQACQgAAoAAdbZ0BBgc4qfhR+wDHAWfDDzL'.
    '278ECNbZRxNAD6AJBAEQetkggC0hAUAAVYAAIAAIAAKAACAACAACgAAgAAgAAoAA'.
    'IAAIAAKEJdLPwgigAoAAIAAIAALgDJl/GdztewHR/rs3JlaAV/a1Pv2+qQDvBEuC'.
    'zQQQaGEBhF9YAOEXFkD4KgAIAAKAAO/iNLC4ABpISwAJ9AAoL4AqoAJsJUHma3lb'.
    'gJ6d/O3+eKoGeoDUMyi7wKcEGLGfVwnWEO4LGJkOi3b4aESLOnuji/DJNW8nwMgS'.
    'Hk2EHte5pQCj1/HVN63ntUUSIM1J4Momcdfwu1cA3Xy+vuaocJGYWAFUg1wT46h4'.
    '0ZhUAVSC+BNh2sCqShC9Ck4fXCURMiyBywa4uwhZ+p/lg9xNhGyNb5jBZhch644n'.
    '3KCziZB9qxt28FFFcLYBAAAAAAAAAAAAIAe/GU47BhOL5TYAAAAASUVORK5CYII=';

  $cifry[4][0] = 
    'iVBORw0KGgoAAAANSUhEUgAAAIAAAACACAYAAADDPmHLAAAAAXNSR0IArs4c6QAA'.
    'AAZiS0dEAP8A/wD/oL2nkwAAAAlwSFlzAAALEwAACxMBAJqcGAAAAAd0SU1FB9oK'.
    'EQgSNf0wWPoAAAAdaVRYdENvbW1lbnQAAAAAAERtaXRyeSBZLiBLYXphcm92ddNE'.
    'LgAAAgRJREFUeNrt3MFRw0AQRFFJ5TScAzfyP/pGDgRiAgDK2i2tpZ1+/8gFSv2n'.
    'dyRsLQsAAACALFaX4Df3z6/nXz//fnysBAgNv6oEBGgIv6IEm9izIQABQAAQAAQA'.
    'AdwCEkD4BMD/VHsSeDP5dgDhE0D4BEDc+b8sgf8N7J3+iuHHCSB8R4DwUxugZ/qr'.
    'hx8jgPCDBRC+HQCpDWD6gwUQviMAqQ1g+oMFEL4jAKkNYPqDBRC+IwCpDWD6gwUQ'.
    'viNA+KkC+HCnBjD9BBB+pADqP/guoDV80x98BAi/mACqXwOAAOo/UgD1rwFMPwFA'.
    'ABzGNDXZcv6rfw2AVAFMvwYAAVBKAA+ANAAIYAGMFED9BwsgfA0AAjj/R3JzCY49'.
    'nmYTcZvtAl/9b5ttbylxBLxz6vYEPJMElkBLoPongPongOknAAig/gmg/glg+gkA'.
    'Aqh/Aqh/AoAA6p8A6p8AIMDZ9W/6j2eqj4TZFYo1gEDPZxV+9vJKgAuK8eraHCkT'.
    'AcJbxm1gIXqGigDhEhDAbaCtmAAk8BwAde5aWgbLDhDeVgQYKMwZ0rT+Tu8HOCGQ'.
    'UcdJj3B2gAF7wIjJH/V4WAOE7yB2AM8BQAAQAAQAAUAAEAAEAAFAABAABAABQADs'.
    'xuvioQFAABAABChExe8GEEADgAAgAAiAQHw1rIN3vsWLAJNJMNut4g8DyuTmembY'.
    'iAAAAABJRU5ErkJggg==';

  $cifry[4][1] = 
    'iVBORw0KGgoAAAANSUhEUgAAAIAAAACACAYAAADDPmHLAAAAAXNSR0IArs4c6QAA'.
    'AAZiS0dEAP8A/wD/oL2nkwAAAAlwSFlzAAALEwAACxMBAJqcGAAAAAd0SU1FB9oK'.
    'EQgPL/8+zZwAAAAdaVRYdENvbW1lbnQAAAAAAERtaXRyeSBZLiBLYXphcm92ddNE'.
    'LgAAAeZJREFUeNrt3btRA1EQBVHtltIgBzzyN/GUA4GAhQcU6KHdmbmnTUx1z/tR'.
    'iMsFAAAAQBabj+CxPL3c3r/6+dvr8yaAUPmVIhDASfKrRHCl6hzxVRBAqPhPdupy'.
    '5QsgXL4tIFi8W0AB+a6BwVPvIYh8W0DaPl9JvAAOPuBVlC+AYPECIF8ASXu9AEy9'.
    'AMgXAPHJAZAfHMCk93sBnDT10+SPD8DUhwZg6oMDID80AOKDAyA/MIDk9/v4AMgP'.
    'DsByHxwA+cEBkB8cgBe94ADIfyzj/zSM+MYrwOr0k984gHvkE/539omTj+YBWO6D'.
    'twBLf3AA5LsGtqP6F0G2WQE6Tn+HL4IsH8C9J/7q8rtEsHeUjwEBrMjvMv0CGCjf'.
    'CgABmH7vAIfvm+Q3XgGmyZ8U406+LcB+LwBT7wxQRD4aB/Af8k1/0wDIDw6A/OBD'.
    'oANfcACuef3Zzpp8HMdPw7aRnx2BXweH8N3QCsA1EAKAACAAeAdwFUy7Bi69xomg'.
    't/zlANyr1z58ZwAIAAKAACAACAACgAAgAAgAAoAAIAAIAAKAACAACAACgAAgAAgA'.
    'AoAAIAAIAAKAACAACAACgAAgAAgAAoAAIAAIAAKAACAACAACgAAgAAFAABAAfs+k'.
    'f5QhACsABIBYPgCmzf35g5heXgAAAABJRU5ErkJggg==';

  $cifry[4][2] = 
    'iVBORw0KGgoAAAANSUhEUgAAAIAAAACACAYAAADDPmHLAAAAAXNSR0IArs4c6QAA'.
    'AAZiS0dEAP8A/wD/oL2nkwAAAAlwSFlzAAALEwAACxMBAJqcGAAAAAd0SU1FB9oK'.
    'EQgPO+XkGeEAAAAdaVRYdENvbW1lbnQAAAAAAERtaXRyeSBZLiBLYXphcm92ddNE'.
    'LgAAAaFJREFUeNrt3c2NglAYhlEwtmEP7ux/6c4eLEQbkIAJ9/c9ZzmzmcjD/S4E'.
    'nGUBAAAAAOa39vqH3R6vz6+fv5/31WGbPICtgy+CgAD2Dr4IznXxEQgAASAABIAA'.
    'EAACQAAIAAEgAASAABAAAkAACKALR58cwgrASAF41s8KgAAQAAJAAAgAASAABIAA'.
    'EAACQAAIAAEgAASAABAAAkAA/OnqIzjfSN9zbAWodPD3fieAyQ9+rxEIwCYQAdDd'.
    'qBAAAkAAQxnxzWYBhO8DBGAEkDwGBBA+BgRgBJC8CgggfB8gACOA5DEggPAxIIDw'.
    'VUAA9gAkjwEBhI8BAYSvAgKwB0AAdDMGau8DupxP/h1MvT2FFSD8SkEA4REIwCYQ'.
    'ASAABDDtpQ6D3QdwP6DeCdL9mSaCsqujpXaAqEuORJvAsDNeAJOvFgJwVgsAASAA'.
    'Y0AACAABIAC2uBXc2NEbPaU2jVYAIwABIAAEgAAQAAJAAAgAASAABIAAEABNlXo/'.
    'QABWAARAM60fDxeAFQABIAAEQCDvBXRi70ZPqasFAQwQQclLxS9zoWlxsMyiCwAA'.
    'AABJRU5ErkJggg==';

  $cifry[4][3] = 
    'iVBORw0KGgoAAAANSUhEUgAAAIAAAACACAYAAADDPmHLAAAAAXNSR0IArs4c6QAA'.
    'AAZiS0dEAP8A/wD/oL2nkwAAAAlwSFlzAAALEwAACxMBAJqcGAAAAAd0SU1FB9oK'.
    'EQgQDJADsnAAAAAdaVRYdENvbW1lbnQAAAAAAERtaXRyeSBZLiBLYXphcm92ddNE'.
    'LgAAAdhJREFUeNrt3TFSwzAYBWGb4RrcgY77l3TcIQeBmgJnhliO5f22pWCSt3q/'.
    'pDiwLvg3bx9f31s/v32+r2d/DasYx4Q/iwQvomxDAAJgVP0TQPgEEL5TQDb8GY6B'.
    'GsAmEGWMgHD9a4BBzBI+AeLhEyB69COA1U8Aq58A+fAJAAKU5z8B4uETID7/l8VV'.
    '8MPhz7z6NQAIUK1+I2CH8Gevfw0Q3fkTAEZAeedPAOEbAdVdvwaI7/o1gJXfFkD4'.
    'GiB97ieA8H/xKt5m8MkGMP/Dx8D6hY8GAAEQHQHqXwOAAFY/AdATwNlfA6h/Agif'.
    'ACAACAACgAAgAAgAAuDSArgGvo+HQgeK5h9GBFb/1u+ZoYHsAQZLdnYJCGATiOon'.
    'gQSIh39ZARz/wgIIXwOAACCADWBPAPM/LIDv/2kAVAVQ/WEB/MHHsABWvgZw9COA'.
    '8Akg/J4A5n9YAJc+YQGs/LAAwt+fxPcCiPM3qyDbpxQfBsXlJkBcAgI4BYAAIAAI'.
    '4KjkHsBuuSb41CtqS4Qj3sw9RXxWu6nUAwQ48+iyCbQJBAFAABAABAABQAAQAAQA'.
    'AUAAEAAEAAFAAFwPD4Q8yL2HQs7+HCMBBkrgIVYAAAAAAAAAAAAAAAAAx/ID+/S8'.
    'U0KsBTUAAAAASUVORK5CYII=';

  $cifry[4][4] = 
    'iVBORw0KGgoAAAANSUhEUgAAAIAAAACACAYAAADDPmHLAAAAAXNSR0IArs4c6QAA'.
    'AAZiS0dEAP8A/wD/oL2nkwAAAAlwSFlzAAALEwAACxMBAJqcGAAAAAd0SU1FB9oK'.
    'EQgPGEeDaJMAAAAdaVRYdENvbW1lbnQAAAAAAERtaXRyeSBZLiBLYXphcm92ddNE'.
    'LgAAAd9JREFUeNrt3Mu1gkAQRVFgkYY5ODP/oTNzMBBMwOW3oT93n+mbPKjTt6pQ'.
    'nCYAQCizW/A5p8tte/X3+/U8EyC0+L1KQICCxe9RgkV5yxa/NwgQDgEIAAKAACiD'.
    'NdAzAAlgDSSA4hNA8QkAQ2DK6e9xACRAePG1ABDgX3o+/QQILz4BQlc/Ajj9BFB8'.
    'AsTHPwFAAAIgtv8TILz/EwAEIID435Kvf1b83AFQAoQXP1qA9OiPFkDxgwVQfAmg'.
    '/6duAWlf+JQAop8Aol8LEP0SQPEJoPiZAhj+JIDTnyqA0x+8BfioVwIgVQDRH9wC'.
    'PPSRACCA0x/ZAkz+EgBfsor+dpKpRiItit9OW6qxvg4jQIu7/y//09HXYQYIEpIA'.
    '5CEAwgWw+w8owIgf/hx1TYsbJQFAAKSm25J0gwyAwQnQa/H3TgEtQALojwQQ/wQY'.
    'fZqWGGYAbeMJcd8IKiXBKG1ldQay08QaaAYAAUAAEKArPNyRACQogF8ICZd8+BPU'.
    'swhHJJwIbVSio9obASoLU3uOsQbaAkAAEABjTvkEAAFAABAABAABQAAQAAQAAUAA'.
    'ArgFBAABQAAQAAQAAUAAEAAEAAEwOl4N24l3r4e18mo7ASpI4HcNAAAAAAAAAAAA'.
    'AOzPA7IWwFQlTIJmAAAAAElFTkSuQmCC';

  $cifry[4][5] = 
    'iVBORw0KGgoAAAANSUhEUgAAAIAAAACACAYAAADDPmHLAAAAAXNSR0IArs4c6QAA'.
    'AAZiS0dEAP8A/wD/oL2nkwAAAAlwSFlzAAALEwAACxMBAJqcGAAAAAd0SU1FB9oK'.
    'EQgIDWUfGr8AAAAdaVRYdENvbW1lbnQAAAAAAERtaXRyeSBZLiBLYXphcm92ddNE'.
    'LgAAAf5JREFUeNrt3cFRw1AQBFHLRRrkwI38j9zIgUAgAigX+ra16tdXLpSmd/ZL'.
    'xuJyQZrNJfid1/fP779+/vXxthEgGPyZJCDAjvDPIMFV5PvCnw4B4hAgPP0EiIdP'.
    'gHj4eQHq4WsAdJ8DrJr+6Q+CNEA4/GwD7J3+MwSfFUD4YQGEHz4DuOVzCLyY/qgA'.
    'pl8DmP6qAKZfA5j+6m2g2z4NIPxqA+yZ/kr4p20AB79wA9j74QYw+VaAg191Bah+'.
    'DYCqAHZ/eAWsCL9a/+MFEL4VIPxqAzj1hxvAoc8KUP3VFaD6wwIIP7wC7H1nAHu/'.
    'ugJUvwZAtQFMf7gBHPzCDeCDnrAAwrcChE8AEMD0984AvtKlAYRfbYD/Tr/wNQCm'.
    'C+Cp33N4cQnWijptHW3Tp/+RF/zW33OSBM4Ad5B00jq7mn6HQAc/AswL3/QPFsDk'.
    'hwXwWb8GAAFM/1F42JNAez8qwMrgTf+wFSD847NNCH8iU4TdhN8WwW1g/PBLgLgE'.
    'BHAXAAKAACAACAACLMBj2zncNShPBI8/ECb1zjIfvQ19Myg49Q6BIAAIAAKAAAQA'.
    'AUAAEAAEAAFAABAABAABQAAQAARoc+a/bSSABgABsBRvC4cGAAFAABAABAABQAAQ'.
    'AAQAAUAAEAAEAAHwZLwl7EbO+J/DCbBYAi/IBAAAAAAcnB+6j9/RKk+vuQAAAABJ'.
    'RU5ErkJggg==';

  $cifry[4][6] = 
    'iVBORw0KGgoAAAANSUhEUgAAAIAAAACACAYAAADDPmHLAAAAAXNSR0IArs4c6QAA'.
    'AAZiS0dEAP8A/wD/oL2nkwAAAAlwSFlzAAALEwAACxMBAJqcGAAAAAd0SU1FB9oK'.
    'EQgPET5f0DcAAAAdaVRYdENvbW1lbnQAAAAAAERtaXRyeSBZLiBLYXphcm92ddNE'.
    'LgAAAgtJREFUeNrt281RAzEQBWFW5TTIgRv5H7mRA4FAAkDxY68l9ddXqnDZ0/Nm'.
    'JHsfHgAAAAAAIY7qG398fn3/7u9vL08HAaLFL0lwKH5bgmEKthm6nwAggO4nAAig'.
    '+wmAGIfu794BbJ8Aoj+cAP8tfuW7gKH4lkBEu39LAXR/eAe4RvFL3W8ExIu/lQCi'.
    'XwLo/uoO4MwvARS/mgDu+sMJYPELJ4C5LwFQTQDdH04Ac98IcOyrjgDRHxZA8cMC'.
    'KL4dANUE0P235eLI97/XXl2wrUfAX4vzG/FWv5c4du3+M4q/QxIMne8UYPZvEucS'.
    'QPevL4Bf+IQF8E1fWAAXPmEBduj8Vd/DFkug7ncKQFWAa3V/dQG9uwCOfWEBZil+'.
    '+fg5dL4ESFO/fBq6vy2TY6ARoPsJICIJMDurdP9qkg8fjATQ/WHZh+JLABBgzkjU'.
    '/RLArK4KoPslAHYQQMxKAEzMZdbuv1Vy2C1OFMDmHx4BPmg7ABbgkADnMOvuIQHi'.
    'uwcB4hIQIC4BAZwCQIDQ1osTE4AE4XsAy9YajZHs0HvLMZMAQ/Hb48wp4OTizyYS'.
    'AU4s3owpktsBfjsCPivaNf7HLFz0dXsfMALiEIAAIAAIAAKAACAACAACbIlnFSQA'.
    'CAACgAA/ofCjVgJIABAABAABQAAQAARAB88FfEHlyWYPh4aLDwAAAAAAAAAA9ucD'.
    'uWL6cpF5jusAAAAASUVORK5CYII=';

  $cifry[4][7] = 
    'iVBORw0KGgoAAAANSUhEUgAAAIAAAACACAYAAADDPmHLAAAAAXNSR0IArs4c6QAA'.
    'AAZiS0dEAP8A/wD/oL2nkwAAAAlwSFlzAAALEwAACxMBAJqcGAAAAAd0SU1FB9oK'.
    'EQgPIG+B0A0AAAAdaVRYdENvbW1lbnQAAAAAAERtaXRyeSBZLiBLYXphcm92ddNE'.
    'LgAAAcRJREFUeNrt2z3SqkAQhlG03IZ7MHP/oZl7cCEaauIPiOV0v+ekt27wMc80'.
    'IDBNAAAABNl0/KP2x/P11b9fToeNpW8awLvFF0HjAD5dfBHcbe0BASAABIAAEAAC'.
    'yLgFxASgSwB2vwlAagB2vwmwiOcATgGkBmD3mwCkBmD3mwAIAAEgAAQgAIdAAAig'.
    'Jg+CggOw+CYAqQHY/SYAApjPgyATgNQA7P4mAbgANAHsfgEgALtfAAiApADcAZgA'.
    'pAZg95sACAABsKoSP5IsPf/7EcgEoPoEsPtNAASAABCA878ALL4AWNPOIfjd7WiF'.
    'aWQC/Gjxv/l/Amiw+FUiEICLQEafIgIQgQBEIAARCMCOFQA5AfxzB6a8hm4CmAAI'.
    'AAF0sNbTt6TP0EwAEwABEDn+BRC++NPU7JWwVwvoZdGAAOxupwAEgADoEYALNxOg'.
    'ndGi3jpgJoBdE6zcga1+Pz9azGV3VsUQRpxk5UdrlRBGPY21O7eOFsTo1y8urr6I'.
    'p8PFqd8B3AYiAASAABAAAnALKAAEgAAQAE91eUdBACYAAkAACAABIAAEgAAQAAJA'.
    'APTmu4AHn74U0uljVQHMjMCXygAAAEBVN8NLijv6wxkqAAAAAElFTkSuQmCC';

  $cifry[4][8] = 
    'iVBORw0KGgoAAAANSUhEUgAAAIAAAACACAYAAADDPmHLAAAAAXNSR0IArs4c6QAA'.
    'AAZiS0dEAP8A/wD/oL2nkwAAAAlwSFlzAAALEwAACxMBAJqcGAAAAAd0SU1FB9oK'.
    'EQgPNQJcNOYAAAAdaVRYdENvbW1lbnQAAAAAAERtaXRyeSBZLiBLYXphcm92ddNE'.
    'LgAAAetJREFUeNrt3bFxAkEQRFGWUhrkgEf+Jp5yUCBggAPGcYICdqbfdzGk4n73'.
    'zJ4OsdkAAEIZ3oILu8Pvaen1v+N+ECD04neWYLj46y5+Vwm28p8NAQgAAoAAIAAI'.
    'AAK4B0AAEED6CQACgAAgAK50/HNwpAAWQA0g/akCSL8GkP5UAaRfA4AAIID5nyeA'.
    '+a8BpD9VAOnXACAACGD+5wlg/msALDCkP7f+NQAIkJx+AqCvALZ/DYDUU4DtXwOA'.
    'ANJPABAAYQI4/gWfAmz/GgCpAqh+DQACmP8EAAGkP0wAC6AGkH4C4Bl+pP+9Y2f2'.
    'pilfg8/M/1cvyjd+phFQfOGcdVHdJl4Mv68GcNwkAIkIgLoCePhDAyBVgOrpn2kP'.
    '0AAaQPoJAAJ0Rvo1ADoI4MkfDYBUAWz/GgAEAAGQJ4D5rwFAAOknAPIEcPdPAyBV'.
    'ANu/BkCqAGa/BgABzH8CgADSTwBjigBODgRYKcHMIsw2plp/bZxGeEz7/xJ2L4FF'.
    'MXwJ1Ay3jCrJtf2HNYCqNgKkP10ALRC8A3TaB2YWuVTCqopAgHARCBAuAgGCJfB9'.
    'AeEiECBYigrH2Khz9qeFIEC4QBUEcCs4HAIQAAQAAUAAEAAEAAFAABAABAABQAAQ'.
    'AARASzwQ8k/WPhRS5VNNZ55w0gWihuM2AAAAAElFTkSuQmCC';

  $cifry[5][0] = 
    'iVBORw0KGgoAAAANSUhEUgAAAIAAAACACAYAAADDPmHLAAAAAXNSR0IArs4c6QAA'.
    'AAZiS0dEAP8A/wD/oL2nkwAAAAlwSFlzAAALEwAACxMBAJqcGAAAAAd0SU1FB9oK'.
    'EQgFLwXRJRYAAAAdaVRYdENvbW1lbnQAAAAAAERtaXRyeSBZLiBLYXphcm92ddNE'.
    'LgAAAiBJREFUeNrt3ctxwkAQRVFGRRrkwI78l+zIgUBwAqaAAknT/c5deuES/e70'.
    'fIzGhwMAAADiGF0/2Olye/z38/v1PMTeXIBn4ZMgQIBX4ZOAAGXYQlIChMuwaILZ'.
    'YhMgXIJFgbJZhE8AEMDoJwAIgLyzgKMi1pjG1nruFieBzv+DpwALQGsAEAAEAAEs'.
    'AMMEsAAMFkD4OgAIAAJYAH5FycJ8Mv8LXwdAqgBGf0MBbP+CBRC+DqD9EwAEAAFA'.
    'APN/kgB2AOtwTAz/l7+vercZ3QR4FsjaHaSqCG1fDNl6yjhdbo+KEgxBZncD28Bw'.
    'aYdCZncCAoSLYAoIF1kHCO8GOkC41EOhPhuV3U4RYwX4pvidJIgS4NfF/tUz7inB'.
    '6B7+2sWtLsHoGv7WBf32uQnQYDRVlKDVNvB+PY8951N/DQxsob/8PDpAeDfa45Bo'.
    '6VJszxYqgG8ABwtQJfy9jpqtAVBHgE9Hs9bfsAO8G6rwG54DJDDjtfbWAKYAEAAE'.
    'AAFAABAABAABQAAQAAQAAUAAEAAEAAFAABAABAABQAAQAATAa2a99JIAweETIDx8'.
    'AsC7gbON/q1ffD1WKt7sbwVXvN283E2hs0hQ4SrbkgK8U9hqt4DOGv7UU0BSC3dZ'.
    'dPF5tGr4toEgQPLoJ0B4+BaBwcETIDj0qXcB3XYCs59e+rdxQWGXEmB2CbrcVlri'.
    'QySe0AEAAAAAAAAAAAAAAAAA8CF/IRkKaVfj2uQAAAAASUVORK5CYII=';

  $cifry[5][1] = 
    'iVBORw0KGgoAAAANSUhEUgAAAIAAAACACAYAAADDPmHLAAAAAXNSR0IArs4c6QAA'.
    'AAZiS0dEAP8A/wD/oL2nkwAAAAlwSFlzAAALEwAACxMBAJqcGAAAAAd0SU1FB9oK'.
    'EQgHLzfnR5QAAAAdaVRYdENvbW1lbnQAAAAAAERtaXRyeSBZLiBLYXphcm92ddNE'.
    'LgAAAnNJREFUeNrt3T1S7DAQRWGPyttgD2TsPyRjDywEIshmxhhbkvt+J31VD9v3'.
    'qNXyj2ZZAABAIrcqJ/Ly9vF1798+319voi4swKPwSVBcgC3hk+A+zSVQAWIqQLUq'.
    '8Ozct5zragzsE+kq5/RMglXg9c/zkQRN+HoAgRanfAWwvLMMBAFAAPx5eiRAcPiR'.
    'AqQ0jFvPc00dGUfcRq1A7J1AS0c9AAgAAhAABAABQAAQAAQAAUAAEAAEAAFQG18G'.
    'PaDH6+ajH0urAHeC7/WtwehvGggwQSAjJVgFPsdXRVs+5CRAsdBnkOAm1PnoKYEe'.
    'IFxmAoRLQIBwCQgQLoE7gR2bsxkbVauAzl353uM8a2VgCuh84ff+f2cJbgoYsP7e'.
    '8nGqKaDjCBn1RG7PMR99rM2oH/c4ds/fPrpyNOFnU0KAKz8HGF0FmvCzJWjCz56O'.
    'YnuACvP/EQOgCT/7uNwJDJeAAOHTgI0iCxzjfyRQAcJFbUkXyZ2/ghVga6jCLzwF'.
    'PAtX+PdxYYp0+Xsl1wSaAkAAEAAEAAFQfbVAABAABAABQAAQoGJnTwAQANvwOLhI'.
    '+fc4OJj/vPBCAD0ACIDI8k+A4PU/AUAAECC6/BMgvAEkQPjoJ0B4+AQAAZLnfwKE'.
    'h78sdguPnft/8D7AxcI/eq8DFSBw1OsBhG8KuGL4Z2x1Q4Dg8AlwkZJ/5iZXBAgO'.
    'nwCTN3g9trcjQHD4BAgPnwCTreVH7GhKgMGhjwqeAJ1DnjF8Aixjb83OsIm13w4O'.
    'Dr+MAMIPFuBK4c/4uwXeBwgMnQCC/8ULIcHhqwChoRMgOHDLwLCAIwQ4UgI/MQcA'.
    'AAAAAICSfAMj+ygnYrafWAAAAABJRU5ErkJggg==';

  $cifry[5][2] = 
    'iVBORw0KGgoAAAANSUhEUgAAAIAAAACACAYAAADDPmHLAAAAAXNSR0IArs4c6QAA'.
    'AAZiS0dEAP8A/wD/oL2nkwAAAAlwSFlzAAALEwAACxMBAJqcGAAAAAd0SU1FB9oK'.
    'EQgIODOs3pwAAAAdaVRYdENvbW1lbnQAAAAAAERtaXRyeSBZLiBLYXphcm92ddNE'.
    'LgAAAkpJREFUeNrt3TFOw1AQRVF/K9tgD3Tsv6RjDywECoREASKREntm3rklVfC7'.
    '8779E9vbBgAAAAAAUljT/8Gnl7eP3/7+/vq8xD9cgL/CJ0GAAP+FT4IvdiWYDQEI'.
    'AAKAACAACAACIIqRmyDXbgJ14ZGbVRogXGgChEuwHKjs5WAXvqsAEAAEAAGs/wSI'.
    'CL/rr4Ae8bkvqeZXleDoH7GutOnv3ACWgMGTTwDhE8CZ//GslPBN/6AGMPnBDeCs'.
    'P1gA4VsCQADTTwDhE0D4YQK49NMASL0MvGX61f+wBlD9wQII//FcHILHylZ9SVqT'.
    'Avl5sCu1R2UJ1qTwqy8ZFUUYI0AXqklgHyBc7uUAZTeBBrAPgOSmWw5K9lKgAcKx'.
    'E3jHqezYYBFLwNE12+mehTU5/DMPbJevsP0m0DmAwCu031mf31VAOHuVCZg2/V0a'.
    'SQNogJmXfghpgMpV22EZ2IWvAYRPABCg0SSb/mENcEugwh+6BFwTrPDvT6nvAgTs'.
    'JBAEAAFAABAABAABQAAQAAQAAUAAEAAEAAFAABAABAABQAAQoBwdbnwlgAZA6vRv'.
    'W+PXx08K31PCoAFMvwaw7hMAloCg6a9wMywBTqr9KndCe1x8yFqvAQqFX+k5CAQ4'.
    'eOq9N1DlWwJSg/fqWOFrAOETQPgEmH9y1+WRd0vwueFHCjDl3YQEMO0EOCv0KeGP'.
    'FOCI8Cc903gJOzf8dgKcvSc/8WnmKy1EwZ8oQIfw095ZsISfG/62+UlYbPDf7MLP'.
    'fk3NReDZXISezS78bD4BQcgVsSsTbacAAAAASUVORK5CYII=';

  $cifry[5][3] = 
    'iVBORw0KGgoAAAANSUhEUgAAAIAAAACACAYAAADDPmHLAAAAAXNSR0IArs4c6QAA'.
    'AAZiS0dEAP8A/wD/oL2nkwAAAAlwSFlzAAALEwAACxMBAJqcGAAAAAd0SU1FB9oK'.
    'EQgFFy3TnYgAAAAdaVRYdENvbW1lbnQAAAAAAERtaXRyeSBZLiBLYXphcm92ddNE'.
    'LgAAAmRJREFUeNrt3bF1wkAQhGEdT224BzL3H5K5BwqxIyd+YLAB6Xbnm9CRdfPv'.
    '7N5JSMtCREShGl0u5O394/PS38+n42BzcwCumQ+CAABumQ+C33WwBACgYK1JF3tv'.
    'u+iqS21wMDUbAi0gTD8LBgCGQAIAAYAAQAAg28CmZwDuBVxegzV9AbSAgOqn6xoJ'.
    '5qt8QyABgFq0APEvASg1AVS/BGA+ACgSAAc/EkD8A4AAoPrzAND/X6c11fwtoZo5'.
    'lUYnAG4t9J5JMisEo1P1X1rkmdrHjBCsXcw3LzRLgM5GzpQEg/nZEAzmZ0PgJDAc'.
    '9oMFMQQyP7gVrGrgeQZUhHgkV/8rKu8/17JnAsQBsNViV3l+sX0L2Gthz6fjqNAS'.
    '1o7V75mAIi3gmebPaHqFNlD+IOh8Oo5ZK75CEh0qV7+oDx0CGV88AR6p/mrmz/7/'.
    'jirmV676md9ltKogLaD0np+KAOBunwSgVABEvxZgAAQAAYAAQADQ/wFAACAAiP8Q'.
    'AP5iKPObJsA9xjJ/e216O5jBZgACAAGAAEAAiNHMz0MAQAIQAAgAlCkncxMNgH4e'.
    'TlqA7R8AIrXXjTIABJsPgPD4B0C4+QAgAACAYgfAZXESuGv/98UQw58WIPoBoPoB'.
    'wHwAiH8AqH4AqH4AMH9L+WxcaPR/y0ngRubP+stoLSC08gHAfC1gK/O9LZwkgOqX'.
    'APb9EiCn+qu8EAsAIft9ADDfDEAAIC1A/AOA+QBgvhngYXV4+TUAHqh+uwACgPi3'.
    'DYyL/04fvhjMzzVfC6BsAHzWPrgFJB76SACSAKo/HADmawHMBwB96ws5ES/nO6oA'.
    'tQAAAABJRU5ErkJggg==';

  $cifry[5][4] = 
    'iVBORw0KGgoAAAANSUhEUgAAAIAAAACACAYAAADDPmHLAAAAAXNSR0IArs4c6QAA'.
    'AAZiS0dEAP8A/wD/oL2nkwAAAAlwSFlzAAALEwAACxMBAJqcGAAAAAd0SU1FB9oK'.
    'EQgIIs7OJ+YAAAAdaVRYdENvbW1lbnQAAAAAAERtaXRyeSBZLiBLYXphcm92ddNE'.
    'LgAAAjhJREFUeNrt3LF1wzAMRVHJR2tkh3TZv0yXHTJIskBsH8uxBODfV7oxTTwC'.
    'IClrWRDN2nXgbx9fP399/v35vgrrcAGuBZ8EAQLcCz4JHuNiCggAAoAAIAAIAAIg'.
    'ia3zXh/Pswr+8VQ6pFoFP1sGPUC49AQIl4AAtoEgAAigIyYACQJpM9mTzwjOlH4z'.
    'medKfPZ4twXRctoFaAJBABAABAABQAAQAARACCNPAs+6N+h4keUyKFwCAoSLoAcI'.
    'F5YA4RKsJjK7JMgA4RITIFwCAoRLQIBwRj8T+OrGa0JzehH847+jkjhKwNDtHQEO'.
    'lqCrCAQIhwDh28GRApyVjh/93goSyADhTSEB9ABWIAEEnwDIFJEAMgAIMCClqv/D'.
    'M8CtAAv+flo9DyDQegAQAAQAAUAAEAAEAAFAABAABAABQAAQAAQAAUAAEAAEAAFA'.
    'ABAABAABQAAQAAQAAQiAaMr+O/jaO/T8QzggA9x6geKEV7QTYGfwSTBcAIENFkDw'.
    'ZQAQAAQAAe5R/SygUz9zEfzsZlYJUAJAAMSWMwKE9zIECG4ACQACEACx6b+lABUn'.
    '+tExVTrMkgGUABAAcbW/nACe9pUB2q26PeOoJvraObBnTeZeAStmuW3B2Po+PgP8'.
    '56p6dbCr9jjrhFX3zOQescorN7glBzYt9RIgWILq21sHQcHBL50BumeBLgdbMkBw'.
    '8MtngG5ZoONxdosBV5ag+x1Gm8GfKcHki6pWP+xICVJuJ1v+yFeK4FoaUfwCQSHc'.
    'szpgAXEAAAAASUVORK5CYII=';

  $cifry[5][5] = 
    'iVBORw0KGgoAAAANSUhEUgAAAIAAAACACAYAAADDPmHLAAAAAXNSR0IArs4c6QAA'.
    'AAZiS0dEAP8A/wD/oL2nkwAAAAlwSFlzAAALEwAACxMBAJqcGAAAAAd0SU1FB9oK'.
    'EQgHOlo6o38AAAAdaVRYdENvbW1lbnQAAAAAAERtaXRyeSBZLiBLYXphcm92ddNE'.
    'LgAAAj1JREFUeNrt3b1RxEAQRGGtSmmQAx75m3jkQCBgYYGh+ykxM/09G+PYftOr'.
    'Vd1J2wYACGV1/wde3j6+zvzd5/vrEvcwAc6GT4KBAtwaPgn+ZrcEBAABQAAQAAQY'.
    'fQKABkD3+wCPTL97AOECdOBqSW0B4YITIFyC3cI4BYAApp8AIIDpJ4DwCSB8AgwJ'.
    'v+ut3ys/99iLwJ9F7CbB1Z93mX5bwNjpR8MGeHT6hR/cAMJv3gC+7KEBQADTHymA'.
    'u37B1wD2fg2A1AYw/RoAqQ1g+jUAUhug2rEvtVE0QFEhRzdA9cVOagMChIuwhJ8t'.
    'gWuA8GuDZTGzm0ADhDcBAcIlWBYvezvQAOEyLwuW3QRHl/Cftdi+enZxA1T8occz'.
    'JejeAist/GeL0F2AsheBVyysL5U4BcRL4BgYLgEBHpSg+6mCAOES7JOCgAZAdwFM'.
    'f7AAFcJPE9AWoAFqTFOlyUtqgb3CYtr3/4/DRNkCQAAQAAQAAUAAEAAEAAFAABAA'.
    'BAABQIAckn5ASgANAAIgsv4JAAIkT/+2FX15dLfwO3/nUQNs2c8NIoBrANNPANyN'.
    'p4QFT/+EH7xogODwoxvAOwuDG8DjYoMbwL4f3AAmP7gBPBc4uAFMfnADVHtfgQZw'.
    '5tcA9n0CCN8WAA1g+jWAiz4NUGr6055puISfG/4YAez593OkB+8UIHhvDTP1GiA2'.
    'fC+OhAYw/RogDuEHCyD8YAGEHyyA8H9zCJ4AgrcFCN99gEH3AoQ/VIAzEggfAIBT'.
    'fAN+OR30s7Bm3QAAAABJRU5ErkJggg==';

  $cifry[5][6] = 
    'iVBORw0KGgoAAAANSUhEUgAAAIAAAACACAYAAADDPmHLAAAAAXNSR0IArs4c6QAA'.
    'AAZiS0dEAP8A/wD/oL2nkwAAAAlwSFlzAAALEwAACxMBAJqcGAAAAAd0SU1FB9oK'.
    'EQgHHxE+dzgAAAAdaVRYdENvbW1lbnQAAAAAAERtaXRyeSBZLiBLYXphcm92ddNE'.
    'LgAAAm5JREFUeNrt3TtWxDAQRNGRz2yDPZCx/5CMPbAQCEg587OtT9d9KZnqdbfk'.
    'EfblAgAAAAAAUmiW4H/ePr5+bv39+/O9ESA0/EoSEODF8KtIsIk8GwIQAARALFdL'.
    '8PoGsMLm0ylgZ/ivBnGEbEdIQICdYTwawlndZa8ERkDhsUKAAQHNHjgBwircMbBw'.
    'eAQQPgGETwDhEyATD4KCq/+oR8GOgSFBx3eAVaq/9w0jHSAs8MgOMGP1z3KXUAd4'.
    'IKhZfrrVATpXf5W7/7HPATz0Ce4Ae8NPqP6yAgjfCBB+qgDmfvgI6CmAfw5V/cuL'.
    'YA8QPnYIEC5BE0D2OGjCz5agCT9bAnuAcAgQvikkQLgEmwU2AhAs6dXC3t+9V+40'.
    'jQCPH90qXi+LvxSa9vu/DrAz+GpdYFP1TgHoJM+Mm8lNgDoAgiXakkJQ/YVOAc/O'.
    '1TPC7/16Wc8BVLURQD4CkIAAGfsWAoAAIAAIYCNIABAABAABQAAQAAQAAUAAEAAE'.
    'GIq3hEEHUP0EAAFUPwGwFO7UD6j+mS6Q6AChrZ8Ag5jt+hgBgqufAOHVT4Dw6idA'.
    'ePU7BgYe+3QArV8H6Bn+7P81pAOEVj4Bgjd+BFD9BFD9BIivfgKEV79jYOCxjwDC'.
    '7y/AvYUdvXBHzPxVXxPTZlncEQt45GaPAAcscM9FFP4fMR+N8pHJUAHODn71dwVf'.
    'Ba4DLB3EyMArvC7+umoooyu9yocqlhkBM7X2Sl8padXDErxjoOBv4NfA4PAJEB6+'.
    'ERAcPAHCgz/1FLDSSSD9w5NT/RooeCNA4JU6wKguIOyJBOgtgfAnFOBMEQQOAAAA'.
    'PMkvOX09zxUsPYoAAAAASUVORK5CYII=';

  $cifry[5][7] = 
    'iVBORw0KGgoAAAANSUhEUgAAAIAAAACACAYAAADDPmHLAAAAAXNSR0IArs4c6QAA'.
    'AAZiS0dEAP8A/wD/oL2nkwAAAAlwSFlzAAALEwAACxMBAJqcGAAAAAd0SU1FB9oK'.
    'EQgHJzk8z6YAAAAdaVRYdENvbW1lbnQAAAAAAERtaXRyeSBZLiBLYXphcm92ddNE'.
    'LgAAAj9JREFUeNrt3c11wjAURGHJx23QAzv6X7KjBwqBBnLCnw3Sm+9uc5Jg5r6x'.
    'kO2kNQAAkEif8UUfTpfbf1+/no9dtEUFeBT+NyQY4TVECvDsG791AK/+3pkkWJTg'.
    'fuFv8f0EmDj8GSBAcPittbaKOjN4DSB8AqSHT4Dw8MvvA4yAfYBgZtgJ9CkgNHgC'.
    'hAdPgPDgrQGErwGSgydAePBOAcInQHr4rU20E/jLXcDK9xhqgODwp2mAd6ff3cEa'.
    'AARApADqv4gAyTdrEMD0Zwtg+jWA6U8VwPRrAKQKYPo1gPP/l+gaIFsmAoSL0IWf'.
    'LYJPAeELUw0Q3gYaILwNujc2uwk0gH0A05/cAhogHAKELwgJEM5aeYqeOT+nX3mM'.
    'vyv4ej725KuIvdr0fxJm5b8KXr4BtpjkxCZYqoT/658161piEb59gGnP/8LXAEgV'.
    'YM/pT1oHLMLXAAiWjAAawGQSQPgEEH6mcMsMoZr8wg3wKFzh78sQN4QI2RoABAAB'.
    'QAAQAAQAAUAAEAAEAAFAABAABAABQAAQAATAFqwjvZhnn61zC1nBBnjlwcrD6XLz'.
    'b2UKCfBumCQoIMCnIZLAIhDpAmgBDQACgAAggLUFAUAA008AEMD0E0D4BPgeM16m'.
    'Xk189jbyKvjc6Y8SwAWjwgL8NX0CDxJA2D4FWP0TQPgEED4BhE8A4b/AEAcyyyq+'.
    '4hNJwxzQ6BJUfRxtqIMaSYKU5w/jLwalBT50A/yiBdKfNB7y4PeUwKPlAAAAAAAA'.
    'APK4Az87/5MWaHCAAAAAAElFTkSuQmCC';

  $cifry[5][8] = 
    'iVBORw0KGgoAAAANSUhEUgAAAIAAAACACAYAAADDPmHLAAAAAXNSR0IArs4c6QAA'.
    'AAZiS0dEAP8A/wD/oL2nkwAAAAlwSFlzAAALEwAACxMBAJqcGAAAAAd0SU1FB9oK'.
    'EQgFNmG6jdYAAAAdaVRYdENvbW1lbnQAAAAAAERtaXRyeSBZLiBLYXphcm92ddNE'.
    'LgAAAlxJREFUeNrt3TtSw0AQRVGNy9tgD2TsPyRjD14IRFQRAP7Ikqb7nZs6sDTv'.
    'Ts9HY2tZAAChjCoX+vL28fnf55f31yHOpgJcC58EjQW4NXwSPMZJExAABEAqZ02w'.
    '3/xkxnnKWYiPhbj2O2eRYHQL57eGPSLkKpXgrEebBIIAIAAIYCwnAAgAAiBiD2BZ'.
    'Jt0I6j7+2wrWuxcCCJAAgiaAMK0CQAC9nwAgQMQeAAFAAFgG7lr+/brob0Z1Af4L'.
    'a61QCSKM6r3/Z0hbVZDOIpQXYE86imASGL5EJUC4BASwD4DkKkCAcAkIEC7B0JDZ'.
    'y0MVwCQwt/c7SBJaAS7vr+M7/HQJzimBX/s89SDK4fZv0fCP9uo111K1krQS4Bkh'.
    'pElQegjYosHThoOT8OcYQggAAli/E0D4aauAe8bOvcPv8F/AJSrALQ12RKMmVJtp'.
    'loFKuzkACAACgACWnQQAAUAAEAAEAAFAABAABAABQAAQAAQAAUAArGKTM4HXTtM6'.
    '/9e4AtxylNo7AZoKcE+wJDAHQLoAqkAjAYQZLIDwVQAQAAQAAUAAEOA2PBMIFkD4'.
    'wQIIP1wAG0eGAKQLoAqoACAACDA53YeqpwjQ9e/VE+YpT6sA3SRImaSeNLY5wDQ9'.
    'bhYRvDImuPHXfn+1be4pVwFHVYPEoWhUaMStetWzr7XiQ65RsSc90tB79G4CBJfT'.
    'qo+47QQGh08AbPvWsIRhoPrpppPGUQGss4MF3/0GukjQpbodchOVJeg2rB16M5VE'.
    '6DqfKfXuYME3FWAmEdJWLlPfrP17AAAAAAAAAACAdXwBgrsVxYuwYNQAAAAASUVO'.
    'RK5CYII=';

  $cifry[6][0] = 
    'iVBORw0KGgoAAAANSUhEUgAAAIAAAACACAYAAADDPmHLAAAAAXNSR0IArs4c6QAA'.
    'AAZiS0dEAP8A/wD/oL2nkwAAAAlwSFlzAAALEwAACxMBAJqcGAAAAAd0SU1FB9oK'.
    'EQgHGI9a4psAAAAdaVRYdENvbW1lbnQAAAAAAERtaXRyeSBZLiBLYXphcm92ddNE'.
    'LgAAAn1JREFUeNrt3TFuw0AMRFFJyDV8B3e5f+kud/BB4j4IgthaS0vOmzJFgIif'.
    'M+TK8S4LEeVq9Qj+r8vn1/dvP7/frisAQotfHQIA7Cx8dQg2JR5T/KoCQHDxAUAA'.
    'AADF2r8tYGDxq66BHGCAHAQFd3/l4gMgvPgigDhAcvdzgMChDwCKD4ARe78ZILD4'.
    '3bqfA1CuA+j+YAAUXwQQAHQ/AAgAtgADYKz9xzlA+qmfCKBcAHR/MACKHzwEpn3K'.
    'lwMMUErx2ztA8ke94h1A7osA1p8aAaw/GADv+oMjQO6bASg1AuQ+B6BUB9D9wQAo'.
    'fjAAim8GoFQH0P0cQPEBQHERwPqDARhZ/I6XP7SOgJEvev76XWkvlNa07vefQYbA'.
    'U1wHACAAQNW9vzMEm+Jna1N8AETpVUC6xkDkELgHgm4glADgHQ99T1R0giB6Dbzf'.
    'rmt6JDgHMARSchS0AuBVO0+eBzhAOAQAGDQUAiDcDaq6AADCIQBA+GYAgDcNhgAQ'.
    'BQAgALTIVHcG0dMQVImBTfdnOwEHMANQ8plAGwCOsme3hwcXXwSwz3bAmQHC54Ct'.
    '+kOr0I0zQ8ABwmMAAIZAAgAbjo0BDsABCAAEAAIAAeB8ufmLAxAAMnZwABAACAAE'.
    'AAKAdREAIABAKQh8SxhxgNFywMMB2n3Sd+a/RwRwgDn1TNccPYz5tnBqE2dtAHAm'.
    'wAEOgcCNIZPb50wFqrDNfHS0tZ8QOFsoHAEjimc+sAUMufGrI0itbw8f7S4dbx5f'.
    'q3XxmVEDAPv+AgAQtCl+2SHQWhfuALO7QSVAW3SS079wAGaCAQDBIFScTVoPU0dC'.
    'UHUwjZmmzz5EAkBDGKyjVF4PMP1f34lWy3oAAAAASUVORK5CYII=';

  $cifry[6][1] = 
    'iVBORw0KGgoAAAANSUhEUgAAAIAAAACACAYAAADDPmHLAAAAAXNSR0IArs4c6QAA'.
    'AAZiS0dEAP8A/wD/oL2nkwAAAAlwSFlzAAALEwAACxMBAJqcGAAAAAd0SU1FB9oK'.
    'EQgIG5HLr+4AAAAdaVRYdENvbW1lbnQAAAAAAERtaXRyeSBZLiBLYXphcm92ddNE'.
    'LgAAAqZJREFUeNrt3cFtIzEQRNGhoDScg2/O/+ibc3Ag9snAYgHvyrKG7Ga9CkDQ'.
    'sH5XN2eo0XFQtIYl+F5PL28fZ332++vzAECY6RUhAMAC4ytBcGH9OvMr6ML8XPOP'.
    '4ziujM/WhfnZujLefQDmL5JtYHDFuxHU0PgqpgFgovk7mr4lAGdU/e7mbwOAqg8G'.
    'gPnBADA/FADGBwPA/GAAHmF+uuF/K+phEPMbJ8Bvqp/xzQFgfjAAzDcDEABUf2QL'.
    'uDf+mR+cAMzfJAHuqX7mbwIA87UA5qcC4Oi2BFD9ACAAqH4AMB8ABAACAGUA4B6A'.
    'BKBUAFS/BLAFBABFAiD+12kw3W8DVXswFIPp2SAMpmdDMZieDcNgfDYMg/nZIAzm'.
    'Z0PgTmD4EAyAcLVqAY+O1RVVWq01LAXgazFu/ZwzF687zK1awPvr86hWCbO/U5UZ'.
    'Ydo28F+LWyEBVpm1uhBOvRF0y8X9ZHErpMZuL6pstaDV2sajYFh5XbaBBYx7enn7'.
    'WDUTAKBQ9a6AAADhEFwTF/ns79fpGYkECIS1DABODUkASQCA/SGoDMJFJWVfgwQI'.
    'FwAAQAAgABAACAAEAAIAAYAAQAAgABAACAAEgIXyIgoJAAItgABAAKCNAPDefwnw'.
    'IwgMgloApQMgBSQACHYDwDAoAaRAOgD3pAAI7AJoJwCkgAQAgRZgVzBT110u5M8U'.
    'ANAGQ+BvTNQSbtfoVNndW0rFt6JHbANXvotXCyhUvSBomgCPhgAIjWaAGRU8q+dW'.
    'nAHabZdmVO9ZBgCgYS9fMYMAoOhQN+veBACaTPZnHWgFQMMt3v9MA0AQDF12JdsC'.
    '0B0EAARDMPu5Rcxj0y4gACAcBgAEw7DisbWTM0WAWHVmAQAFYFh5YAUAi2GI//v4'.
    'NCAcWCUiIqIK+gQrVYyWzsA9kgAAAABJRU5ErkJggg==';

  $cifry[6][2] = 
    'iVBORw0KGgoAAAANSUhEUgAAAIAAAACACAYAAADDPmHLAAAAAXNSR0IArs4c6QAA'.
    'AAZiS0dEAP8A/wD/oL2nkwAAAAlwSFlzAAALEwAACxMBAJqcGAAAAAd0SU1FB9oK'.
    'EQgGLln7RkMAAAAdaVRYdENvbW1lbnQAAAAAAERtaXRyeSBZLiBLYXphcm92ddNE'.
    'LgAAAopJREFUeNrt3TFS60AQhGFJpWtwh5dx/5CMO3AQiKh6VRCA0K5np78OSUy5'.
    '/+mZWdnefaMSenp+ff/u728v//aRr7t76+uZPhMCABQ2fgYEBzvqmz9SAAg2f9u2'.
    '7WRLpvEACDYdAOGm2wIWM9wayHwJkGb+aOMBUND8WaYDgPkAqGL+o4wHAPMBkBb3'.
    'AAivdgBMNr+q6QAYaPwKpgNggPmrGf+pk/HZ2pmfW/1xAIyo+pXNjwEgbbUDgKo3'.
    'BDJdAtxS/V2Nbw1A+m4fDQDzgwEQ+b+XbwYFm98qAf5S/YnGSwDm9wHAg53rOhmf'.
    'rZ352W1gV/nZEFgDw9sJAMIh2JmU3Q4kQHgSAMA5QM/K/D/GnRc0SIDZZ/0p0MS0'.
    'gPQz/6UBeNSHPBJSIG4IBEEAAHfHfWcIDuabAeLMtxUEzwBawWIAjLpVQ6uQAFpB'.
    'dQA8+ZMAlArAzKhNPhxqkQB3xHgqBFqAFkDJ5wLLA/DoKX51CCRA+EoIgBsgWDkF'.
    'ABAOAQBsAZScAgAIhwAAWgAlpwAAwiEAgBZQTxWrp+spoQQIhwAAYUkGgMkpUB2C'.
    'I8kMraAZAH74QQsAKgBsBNEAaAMSgLoB0PHApWpSlU2AFSDocCbgwggtgABAsfNK'.
    'GwC0gYYArDJkrZwCWoAE0GMBAAIAGAYBQAAgAGgDADAI5sDZtgVIgYYAuO1DAkgb'.
    'ANQyx8/EGQaX/v8Pb6IZQI8OBndZAK5sBLYCW8CtaXD1MmsAaAlltDPzenWOutFs'.
    'pk418NXInxjWJUU8YAlfW90ZZAvoIRDYAkCQOgNUnguqQ9n2HEAahCdAlTSoDmJc'.
    'lVS+khYAjSBYpQXpkwOgMH8QERERERERERERUSF9ADzQdgAQ01CIAAAAAElFTkSu'.
    'QmCC';

  $cifry[6][3] = 
    'iVBORw0KGgoAAAANSUhEUgAAAIAAAACACAYAAADDPmHLAAAAAXNSR0IArs4c6QAA'.
    'AAZiS0dEAP8A/wD/oL2nkwAAAAlwSFlzAAALEwAACxMBAJqcGAAAAAd0SU1FB9oK'.
    'EQgFJnwNnbIAAAAdaVRYdENvbW1lbnQAAAAAAERtaXRyeSBZLiBLYXphcm92ddNE'.
    'LgAAAs9JREFUeNrt3UFOKzEQhOF4lGtwB3bcf/l23OEdBLY86UlAMmO3u75ao8gz'.
    '/XdV21HM7UZEuRrJD//y9v5xxuf8/fM6ABBY+A4QDEW/XpXhGIqfDcFQ9GwQhuJn'.
    'QzAUPxuCofjZEAzFz4bguFE0uMNL/H03djpIGor/XAHOhGEFBPdk+z3jhf/vM3aa'.
    'R47U7r+y23b6XmAkFX92YXZY41D8WuudvU7bwPBt4dD9Ndc9a82H4mc7gQgIV2sA'.
    'KnV/VSc6FD87BkRAOJRHx26o3P3V1nZ0Kz6JAAKAWSAWgA7TPwBomsp3S5fp/5Fn'.
    'm/E8R5fid4urWTDfO1JtZlkEwE869uvfgGG97jOLD4bGAJwJ0CMwAGjxLsAFDM4B'.
    'CAAEAMqcAVbPAWaCAgBUggAIiwCoCgYYFgMgIgBQDgYgFPk62CFSOACrYUiGoOyD'.
    'c4U5chBUfCsLABCIgO/semaxusVDCwBmd24nCFr+LuDqAnWKh7b3BH6FwG8LgyJg'.
    'Rdf6p1EbAAAE28DLi7Rj1MQ5wKyi7eIG8QdBVxVqFzeId4B0NwDAJBhcExceDVUj'.
    'AQDfQLBDxIiAguvaJQ44QGG4ARC0bQRAGARVXOCunM9DsPO3jRyg4G4BACHRUME5'.
    'ABA+JAIgXAAIPxMAQHgMACDcBQBgBqDkGAAAB6AKLrBqDgAAB6Dk3QAAwodBAIgA'.
    'So4BAITHwKFjRAABgAukPg8HCJ8DABAOweFlmQF0DAAMTgAAAQAIAFwAAPsPgi9v'.
    '7x9AEAHcoAsAz2wHQdDEAUAgAigdACeDHOBhCMRAowh4BgIg/KutLXXHSxurXYC5'.
    '9RB41nVtya5gFzBxRnBX8EYv9goL/s06Z0XA0MHzigGAJla+y3xjBlD8PACcFnIA'.
    'ECTPALvPBbPhjeuU6jAAIBiCFdEVnZWVQFg1txiWCsCwcmgFwGIo7FiIiIiIiIiI'.
    'aJY+AXG7wFQ62WXrAAAAAElFTkSuQmCC';

  $cifry[6][4] = 
    'iVBORw0KGgoAAAANSUhEUgAAAIAAAACACAYAAADDPmHLAAAAAXNSR0IArs4c6QAA'.
    'AAZiS0dEAP8A/wD/oL2nkwAAAAlwSFlzAAALEwAACxMBAJqcGAAAAAd0SU1FB9oK'.
    'EQgFHyMIFboAAAAdaVRYdENvbW1lbnQAAAAAAERtaXRyeSBZLiBLYXphcm92ddNE'.
    'LgAAArJJREFUeNrt3TFy3TAMhOEnTa7hO6Tz/ct0vkMO4lSZcelEEglgv+1d+O3P'.
    'BQhS0utFRLk6/ATP6u394/Nf/+b3r58HAMJM3wUBAAqZvgOCk3U1zV+lH+zLMlwC'.
    'MF8CMF4CMN8uoLb5toHBq94gaLjpKw0GQBHzK5kOgAXmVzUcAMwHwBPmdzL9qwyC'.
    'Qo2PT4Crq7+78dEAMD8YgCvmTzJeDxBu/F85DAo2Pw6Ayad6ALD6NYF3rv4E8yUA'.
    'ASBdETEn/iUA81MBsPULLgGiXwIQACiyBIh/CcD8VAB0/hLA6v+mXAj5ZmJMBeVI'.
    'NPOrqdVf4gSAovV/CgSxPcBVA9/ePz4nNJun1Z+94zAHCIcAADdC0BGEM3EVPtnA'.
    'dYMgOgGeAqETBCaB4ZPAMf/83SWg0wuf4wF4sv5Pf5L4SDL/iiFTQYjqAa6YcMfk'.
    'EAADALoCQkUIWpeAnde+ppSEM2n1doYXAAOBqgBB2xJQ5dbvXSbuSigJUMS4XWkQ'.
    'kQCrVlfH6aEEGNxojgWg8mlbNwiO6QDsNKTDSymVgKJpsCrlABAOwenHz+4LJEB4'.
    'wwuA8FIAgHAIABDeDwBgEwTuA1CJNABAOAQACBcAAEAAIAAQAAgABAACAAGAAEAA'.
    'IAAQAAgABAACAAGAbtbuB10BIAEIAFReT10eBUBw/R8PgC+ISgACAEUDMKEMPPn0'.
    'kASQAPNXhGZQAoBACagFgdfFb2qMJMGgBEj/5p8S0DR+K6XQwdD1SVLpXcfmAK++'.
    'X/4GQMNorgbawby1MVzlW0fjAFixulZ/KWxFbzJyG7UiZv/HnGqrfywAFWtt1d3J'.
    '6EFKZwhWbU0jJmkdQQBAOAwACIZg5WQy+jClIgirx9Lxp2mVINhxuuk4tQAUO4+1'.
    'AbAZgt13GgCwEQgXWoiIiIiIiGix/gBIA3X4mJGqEgAAAABJRU5ErkJggg==';

  $cifry[6][5] = 
    'iVBORw0KGgoAAAANSUhEUgAAAIAAAACACAYAAADDPmHLAAAAAXNSR0IArs4c6QAA'.
    'AAZiS0dEAP8A/wD/oL2nkwAAAAlwSFlzAAALEwAACxMBAJqcGAAAAAd0SU1FB9oK'.
    'EQgIBWvEko0AAAAdaVRYdENvbW1lbnQAAAAAAERtaXRyeSBZLiBLYXphcm92ddNE'.
    'LgAAAshJREFUeNrt3TGO3DAQRFFJmGv4Ds58/9CZ77AHWUebGTBmRUndXa/yAUTW'.
    '72KTFEb7Rv/Uj19/Pr/zu4/fP/dO4zxYvc78jgJAsPnbtm0vlmcaLwGYD4B086OX'.
    'gHTjv7Qzf526bQFjALij2juaPx6Au2K+q/mjAVD1wQAwPxgA5gcDwPxgAK42f5rx'.
    'X3IXEGr8qARwsPN9uQwKNn9EAlxR/SnmtwdgtflJxrcHYJX5iaa3B4D5mkDmpybA'.
    'iupnfnACML9xApytfuY3BoD5wQAwP7gH8PauJlDTlwqA6pcAqh8ABADVn7cNPLP+'.
    'M785AMwPBoD5wT2AbZ8mkFIBUP0SwLYvFQDVH7wL8IpXMADMDwaA+XoA5qcCoOkL'.
    'XgJEfzAAzLcEMB8AzAcA5fUA7vklAKUmgOqXAMwHAPMBQHkAOPevrbb/Ffw0WL4X'.
    'cKFJ/5vciqnSFYhD5a97ro7L3SvNqLsBrZ4MdgHhyVAOgKn7/6ogSIBwEI6rBqn6'.
    'e/RIEiA8DcoAkHz2/yQER7fBTG4SYxPgXVPdFK5T27uAuyCYfqDlw5FvgjYNiEO1'.
    'vA/EpCXoVa3CJINzAMmQAsDEbr4bCMsA8OpXT7gtAeE6VD8ACAAEAAKALhkABADV'.
    'DwACAJ1Tl7MRAEgAAgA9rqcaYwBIAAIARe4AAEAASK5+ABAAACAmAQCCZ8f05O3o'.
    '0XHCaFAPMAWCrn+LU+bj0Z1B6Pzsh4l87pkrvBm1BICVA9EXOAdoA8EEWPfqE1L1'.
    'BdIpX0F9dauyKW8UVxnHXrEyqk/k2TFWgnivOEGVJ3SS+ZcBUKFJumKiJ379POa/'.
    '9s5O/ETzbwPA/r5u83rrQ6VCUHnn0q6DZn5zANJAAEAwBB0OrfxRZLjKGDAtDbrA'.
    'XfYhOwPRKdnaPGgXILota+3W4MogdOxp2jdhVYDo2tCO68ITrqOJiIiIzuovZQGU'.
    'tDCAaOkAAAAASUVORK5CYII=';

  $cifry[6][6] = 
    'iVBORw0KGgoAAAANSUhEUgAAAIAAAACACAYAAADDPmHLAAAAAXNSR0IArs4c6QAA'.
    'AAZiS0dEAP8A/wD/oL2nkwAAAAlwSFlzAAALEwAACxMBAJqcGAAAAAd0SU1FB9oK'.
    'EQgFDz6/Bd4AAAAdaVRYdENvbW1lbnQAAAAAAERtaXRyeSBZLiBLYXphcm92ddNE'.
    'LgAAArBJREFUeNrt3cttHEEMhGE1sWkoB9+U/9E35eBA5KtgwICknUeT9VUAq+mu'.
    'nzVka3fm5YWIiIgStdI34PXt/eOIz/nz+9cCQKj5nUFYzD5XuwOxGJ8NwmJ8NgiL'.
    '8dkQLOZnQ1DMdw7A/OAUKOZLAOYHp0CpAQmg+r9RmdOOjwHwQzOO/vt3QbDSzT9q'.
    '45+9JgBcDMAZG94RgmL+rLl+NAA7m98VgmJ+tor52SkQcRDUxZA7TjaL+Xv93ash'.
    'cBSsB5gbi5o+CUCpAOxQ/R0SqJgvAYxFwTAW8yWA+N/omq6+flPARhDcAe+DNdnp'.
    'VDZYD6ABDNaaYr7qdwug7gCI/mAAmB/cA3T9Tr0EoPZqfxD07G/40tPj9sWfEf8/'.
    '+cxUEBbzs4FYk8w/Y5qYDkLLJvBKU6aPp9Wx+l1vYwC6zvxTISibmA1Bqx5gh4Zs'.
    'GgRl47JV06r/ipSYBHNNMj9ldj9SLebpXZ7kNRG0mlb9Vxo24VawdQJ0uZ93ToKV'.
    'bD4IfCEkvmmsDtXVAYKu/YAECE8CAIRPBQAIT4HasSLuNiEJAgkQrqX6Z6+hVQL4'.
    'J45bgIYQAAQAKQAACgNAAygBCAAEAAIAAYAAQAAgABAACAAEAAIAAYAAQAAgABAA'.
    'CAAEAALAmfKwaJIAqh8ABAD6irr9vgEAEoAAQACg5yeAjr9vBIAEIAA0jVwalgC7'.
    'QJAE43ZvD/+8+R4aEd4DvL69f6Q8bRwAm0RyWi/y6HKh/xpzRsUlNqJtx8Cjbw+p'.
    'U8ij+wKuSIap9/9TE+DOt3w7U/i6Yl6z+j8gvTYurLP+bNZR1wWA4Car+2FV2Sg9'.
    'wDYdvOoPA6AzCFMSbatFOPcPB6ADBNN6mRaL2XWMBEAYBBMnmdYLuhKKqWNs+0Wd'.
    'DcH084uRi0s/348H4FkInFpSjP4Cp0aKhBVEROAAAAAASUVORK5CYII=';

  $cifry[6][7] = 
    'iVBORw0KGgoAAAANSUhEUgAAAIAAAACACAYAAADDPmHLAAAAAXNSR0IArs4c6QAA'.
    'AAZiS0dEAP8A/wD/oL2nkwAAAAlwSFlzAAALEwAACxMBAJqcGAAAAAd0SU1FB9oK'.
    'EQgFCKDbkH0AAAAdaVRYdENvbW1lbnQAAAAAAERtaXRyeSBZLiBLYXphcm92ddNE'.
    'LgAAAkVJREFUeNrt3UFuwjAQheE4yjW4Q3fcf9kdd+hB6KK7SqgSchrPvO+/AJD5'.
    '53nsENg2AAAAAAAARDBSP/jt/nhe8bpfnx+DAIGFX1WIofDZEgzFz5ZgV/xsdsUn'.
    'gOITQPEJoPgEUHwCKD4BkHIGsG1NDoJmdv/sYvz13hwFLyLAajdpCKD4ZoDu668E'.
    'uLj704tfWgDFtwQofqoADn0kgO5PFUD3SwDdTwAQQPfnnQO8u/4rvgRA9QTQ/RIA'.
    'qQLY+0sAEMD6TwDk7QJM/xIAqQmg+yUACAACIG8GsP5LAKQmgO6XACAACAACWP8J'.
    'AALofgKAACAAJtPqJNDMQABCVBfgymcAEkUYip8twlD8bBHsAopJ2TIBVr/QnZNg'.
    'KH62BEPxsyUYip8twa742dgFhMt7dLiAr2JZ0iw4A/ynAGe+dpdZIO6nYt0JNANM'.
    '6d4uy0vsEEgCuwAS2AaSwDlAuAQEaLatI8CFVEwBAoSnwO4iz31/1VJg12nZtHgu'.
    'YLZQSf9IbgYI3xYSIFwCAoRLQIDwgZUA4RAgPAUIIAGQnAIECJeAAOESEMAMAAKA'.
    'ACAACAACFMRTwBIA6QJIAQmAdAFu98dTEkiAU0XoJtiSAsy64SINzAAkqCrAzNuu'.
    'JHjNkfJBf0vwjmAdRVp6CTjzyxd2DD+Mit17hXRd/9L+2BA9L5TYBXic3DaQBMkz'.
    'QNWYriBt6a7yX0PhAqwsgV8IcaHNAKlpUEnKVjeDpEF4AqyQCtUkjOqYs2WomEDR'.
    'kTlLCEsPgJp8AxHoNSScLUQUAAAAAElFTkSuQmCC';

  $cifry[6][8] = 
    'iVBORw0KGgoAAAANSUhEUgAAAIAAAACACAYAAADDPmHLAAAAAXNSR0IArs4c6QAA'.
    'AAZiS0dEAP8A/wD/oL2nkwAAAAlwSFlzAAALEwAACxMBAJqcGAAAAAd0SU1FB9oK'.
    'EQgFAK4AGE8AAAAdaVRYdENvbW1lbnQAAAAAAERtaXRyeSBZLiBLYXphcm92ddNE'.
    'LgAAAmlJREFUeNrt3cttw0AMRVGPkDbSg3fuf+ldekghSQVBAFmSSb5z94Gt4eVn'.
    '5Il0uwHIZU27oM/H188Vn/P9vC8ChAZ/khBL8LMlWIKfLcIS+GwRluBnS7AEP1uC'.
    'JfjZEizBz5ZgCX62BNsN0SzZn10FPtKM37v40yRsUwGOXPgjMu+o71OlCkQIcPRi'.
    'T5JgTQ7+2QtcUc4xAlQP/hQJRm4Dr1zQ7gdDNsHP3ua6ERReBTaByJagpAB+8VMB'.
    '4oYxAjTP/q4iGgLDJdhkvxYg4978ndwJDG4Ffgt4sQWY/odUAP1fC7D3TxVA9qsA'.
    'IAAIgLxt4N7+bwBUAdC9Ash+FQAEAAGQNwPo/yoAUiuA7FcBQAAQAATQ/8OGQI90'.
    'J0C50z/JYhAgXAQChItgF9BUUgKQQAvQFghABC2AwAQgQY8W4LHvKoBKkFoBrnz2'.
    '79WB6VwJyr8wYs/i/vU3/gs5vAV8P+/rjGztLFbkDECCJgKc2VudDSguwBUBIkHg'.
    'W8P+kyBtUHQfILwqECBcAgKES0AAuwAQAAQAAUAAEAAEAAFAABAABAABIkk5F0AA'.
    'FQCp2U8AEIAAIAAy+39pAboFousZQhXgAOk6HyAlgBkABDAHEMCQlHld5VuAKjBI'.
    'gL3ZUlUCTwmDCqAVEGAEqYJdLsArffOsICVXl02magEgQK/tkyowoAJUkSBdptYt'.
    '4PPx9fOuAE65tb0qBPGdQdnz+ZN+1xj1oEizQcMW4HGtZgASGAL74LVxFlYFIAEB'.
    'SDCEsotdcUs3Uc7SF1RJAi+PDhZhcltqeWFXCjF9Jml9cWeLYCAFAAAAAAzjF6EQ'.
    'Q0ASjJKHAAAAAElFTkSuQmCC';

  $cifry[7][0] = 
    'iVBORw0KGgoAAAANSUhEUgAAAIAAAACACAYAAADDPmHLAAAAAXNSR0IArs4c6QAA'.
    'AAZiS0dEAP8A/wD/oL2nkwAAAAlwSFlzAAALEwAACxMBAJqcGAAAAAd0SU1FB9oK'.
    'EQgHAwU/K3cAAAAdaVRYdENvbW1lbnQAAAAAAERtaXRyeSBZLiBLYXphcm92ddNE'.
    'LgAAAiZJREFUeNrt3UFOwzAQRuG6yjW4Azvuv+yOO/QgsEJCCIIItWPP/70tEjCe'.
    'l/HYSZzLBQAAAEAW7aw//PTy+vaI33O/PbfZBvVRsY2Ir60+QDOJ0COu3rG1KoN0'.
    'tgy94+oVU0kBRksxKqYeMWwpzc5Z4s3O1RAQAAQAAUCA1btxFFwFfBVGN7/4PsCR'.
    'JH5XNdJEKLMRdDR5ewNQXYae0+ZWdYCqSFHyZlCPKjDT/7xSo3utavYMwhIABLAn'.
    'QIDlJai8yjAFhFc1ApgCQADENrXXVQbOiqF4BdhLsOT3Y6p7ARKtBwABQICJqP6s'.
    'AQFUAKRe/QQITz4BLGEJoAdA7NVPgPDkEwAEIAAIAAKAAPgg6c1jAqgAIAAIgEw8'.
    'hPmPBrDCQ6wqgCkABAABQAD8gSpvMRHg4ApABQABlH8CKP8EwMpsVa9cZw0ECLBX'.
    'tj//jAw/0yom/0gjV/EgaD3ALwL5+ogmMP4TNFYB4RIQwBSAo42jVUBo6a4kwvKB'.
    'rDp/zyJRGZOJEC4AKQhQSoZREsTtka8kwggJom+SrCBDbwmi9wHcJbQRdCEACIBc'.
    'tuTgR74JPGvD2SR/fMM409NHpoATEjHTZ/IIMJlU99tzG7k83aSiTmVRAYomiQAg'.
    'AAgAAoAAIAAIAAKAACAACAACgAAgAAgAAoAAIAAIAAKAACAACAACgAAgAAgAAoAA'.
    'IAAIQABDkI2jYndIOEk0/qhUXx4FAAAAkMU7T+/XQikxHd4AAAAASUVORK5CYII=';

  $cifry[7][1] = 
    'iVBORw0KGgoAAAANSUhEUgAAAIAAAACACAYAAADDPmHLAAAAAXNSR0IArs4c6QAA'.
    'AAZiS0dEAP8A/wD/oL2nkwAAAAlwSFlzAAALEwAACxMBAJqcGAAAAAd0SU1FB9oK'.
    'EQgEMJHCGaIAAAAdaVRYdENvbW1lbnQAAAAAAERtaXRyeSBZLiBLYXphcm92ddNE'.
    'LgAAAglJREFUeNrt3LFVAzEQRVGtjtugB2f0HzpzDy4EKljWPoBXM/++lGilN39G'.
    'EjAGAAAAAAAAItgswfv4+Lx/7f3scbtuBDhYpO6cIcFm87MlmDY/m2kJCAACgABw'.
    'D2AAzDsGSoDgzSdA+OaPMcbFop3b1s7+jrIJsPLcUGmmKd0CDI9NBPhNDJKgSQJ0'.
    'kaCakEu1gMftuq0+3HUbZGeXhVmh8iq2o9mpOswDYaeAPQkqiLBKq5tdF+ndElRN'.
    'n9m5UlbdlJUGXW8BZgDHpdT4L5UAq0rw6uav9h2lWkDSJREBCkhQvfrHKPynYc8s'.
    '/n8ueIfNL30KOHo30C6aJ4Dodw/gyEcAaAGLV//qs4gECDmyEkDv1wJEvwQQ/QQQ'.
    '/QRQ/QSw+QSAU8Df9f+qj08SIBwChE7/BAgf/ggAAqTHPwHC458A4dVPgPDqJwAI'.
    'kBz/BAiPfwKAAARAbPwTIHwAJAAIQADE9n8ChPd/Agz/XlYCBMc/AUAAAuj/BEBm'.
    '/ydA+OZHCyD+JQAIgEgBxL8EMAASAARAngD6vwTQ/wmASAHEvwQQ/wQAAVQ/AUAA'.
    'EAAEIAAIAAKAACBAX7wDSACkCqD6JQAI8BxJD0ERAoj/n9lsfm71awEgAAEQG/8E'.
    'AAEIAAKAACCAyZ4AIAAIAAKAACAACIDmRJyTj34xJPm+IObD9yRwWQQAABL5BvkR'.
    '1ehmvnnNAAAAAElFTkSuQmCC';

  $cifry[7][2] = 
    'iVBORw0KGgoAAAANSUhEUgAAAIAAAACACAYAAADDPmHLAAAAAXNSR0IArs4c6QAA'.
    'AAZiS0dEAP8A/wD/oL2nkwAAAAlwSFlzAAALEwAACxMBAJqcGAAAAAd0SU1FB9oK'.
    'EQgGNz2Q7oMAAAAdaVRYdENvbW1lbnQAAAAAAERtaXRyeSBZLiBLYXphcm92ddNE'.
    'LgAAAfBJREFUeNrt3TFygzAQQFHEcA3fwZ3vX7rzHXwQ0mcSGJMgVrvvt5kUoMdK'.
    'JpOkTR26PV7rdEHv571N2qxlBgAXACABAMNWs9sepyselJb54kwCE8Ak2GkZ6Qkw'.
    'RQbeAj5dwL0xmB1Dr21gHvkGedGTdAs4+qTYIoJvAZ8sUtSnuxeyXtffIt/A0Uf8'.
    'USw+BhYe6b3RLyM8RdkPe1denxdBxafXPIJ2p/tkE8Dn9+IABIApEOQ+zJUvXrYA'.
    'ANwCAASAAAh8EHRYTD4BthbY4p9bmB8GWWhnAAEgAASAABAAAkAACAABIAAEgAAQ'.
    'AAJAAAgAASAABIAAEAACQAAIAAEgAASAABAA2mpxC/bL/JdKTYDCiw9A8cUHQAAA'.
    'oNLbjD/MFGhx/PNo4FYTwKJ0nQQAFEcAQHAsZ0MAYAAIZyIAYKBt4wwIPgUUR2UC'.
    'DHpw/K9pYAIUB2YCJPjo+JdpAECidwdHMABwMYD3896uPC84AwTo7Jc9t8dr/Q0Z'.
    'AIEQ9IAAgGmwAmAaAGAaAGAaAGAaAFB8GvjVsGTTYO+l0vfvNQEK4fjpayZA8fOC'.
    'CVA8AAAQAAJAAAgAASAABIAAEAACQAAIAAEgAASAABAAAkAACAABIAAEgAAQAAJA'.
    'AAgAASAABIAAEAACQAAIAAGgUH0Bvj3DE1qRfs8AAAAASUVORK5CYII=';

  $cifry[7][3] = 
    'iVBORw0KGgoAAAANSUhEUgAAAIAAAACACAYAAADDPmHLAAAAAXNSR0IArs4c6QAA'.
    'AAZiS0dEAP8A/wD/oL2nkwAAAAlwSFlzAAALEwAACxMBAJqcGAAAAAd0SU1FB9oK'.
    'EQgEKfWpsWIAAAAdaVRYdENvbW1lbnQAAAAAAERtaXRyeSBZLiBLYXphcm92ddNE'.
    'LgAAAe1JREFUeNrt3LtVAzEABVHJhzbogYz+QzJ6oBCISPnYXlhp7lTgs2/0JK20'.
    'HgMAAAAAEGKe4Uc8Pr++7/yQ316eJgGi4Z9dgin8tgQX4be5eAQEAAFAABAAvW0g'.
    'AcLhjzHGgwfY3spqgHD4BAABCAACoLkDIADOL8DZRxABQAAQ4JBqV/+bN8BXAQv/'.
    'b/j3swBBWwOAACAACAACgAAgAAgAAoAAIMAJ2enLZgKEwycACHAkK5x0EiBc/wSI'.
    'h0+AeP0TID76x1jk83DBH4f7eAeEv9I9RwKEwyfAnSt/xRvOFoGh+V4DHBj8qt83'.
    'TOF3w88KcM+6X/3Lpin8ZvA5AYz6sADCDwug8sMCCD8sgPB/htPAaPCfeBUcDn/b'.
    'KeDa+i/+Xc0Ufjf87QS4Jvz6n1RNo77NpRo+NmiA0tUtAgjfFKD2CXAzRv/iU4Dq'.
    'Dwsg/Puz/WGQ4DdpAG/5wgIIP7wLsOWzDTTvEwBJAdS/BlD/1V2Alb8GED4BkJwC'.
    'flv/Rn+4AYRvCsBOAtj7awD1XxXA6NcARj8BQAAQQP3HBLAA1AAgAJICqH8NYAFY'.
    'FcDo1wAgAAhg/ieA8AkAAoAAIAAIAAKAACAACAACgAAgAAgAAoAAIAAIAALgFk5x'.
    'zeq7q+Gug20uwFcSCB8AjuIDiRzN5/TuMlgAAAAASUVORK5CYII=';

  $cifry[7][4] = 
    'iVBORw0KGgoAAAANSUhEUgAAAIAAAACACAYAAADDPmHLAAAAAXNSR0IArs4c6QAA'.
    'AAZiS0dEAP8A/wD/oL2nkwAAAAlwSFlzAAALEwAACxMBAJqcGAAAAAd0SU1FB9oK'.
    'EQgHCnzjk9MAAAAdaVRYdENvbW1lbnQAAAAAAERtaXRyeSBZLiBLYXphcm92ddNE'.
    'LgAAAbNJREFUeNrt3b2twkAQhVGMaIMeyOg/JKMHCjEVIBvL7Ay+54sJntjD+O9p'.
    'fTpJkiRJkoKaOv5R1/tzTlqE1+M2ARC6+NUIJoufjeDsKJj9AwAgPAAAEAACIP16'.
    'GAAIIvNlN7q0cx8guKrJB4BzAAEgAASACq8AALD4ALgEBMCvHwABoGF5FlA8/qsf'.
    'fpkAoSd/AISf/AHg1w+AAIge/wCEj38ABAAAih3/AAgAAASAABAAAkAACAABIAAE'.
    'gAAQAAJAAOiYXXwFn1v6R88jbGlnAmxc/LWfAeCgi38UBAAMBgOAABAAAkAANC1p'.
    'y3oAwhEAEI4AgHAEAIQjAMBVgJKngI0iv2yP+/6dMAEQjgCAQgh2CnVeYAIkTwMT'.
    'AJrZBLCQXhkjhwATxCHA4lUcEkyA8IkAQDgCAJwEKnkKABCOAACHAI2o64MjAMIR'.
    'AFCAwEuj5MWRWofg11AAaIxgxJSwSVT44cAEcB9AAAgAASAABIAAEAACQAAIAAEg'.
    'AASAABAAAkBb6vpeIQBMAAEgAASAABAAAkAAaGX/vmO4jSJ3aulOX1coAAxAkPQm'.
    'UkmSJEndewMppo/G3OayuQAAAABJRU5ErkJggg==';

  $cifry[7][5] = 
    'iVBORw0KGgoAAAANSUhEUgAAAIAAAACACAYAAADDPmHLAAAAAXNSR0IArs4c6QAA'.
    'AAZiS0dEAP8A/wD/oL2nkwAAAAlwSFlzAAALEwAACxMBAJqcGAAAAAd0SU1FB9oK'.
    'EQgHEfaGWj8AAAAdaVRYdENvbW1lbnQAAAAAAERtaXRyeSBZLiBLYXphcm92ddNE'.
    'LgAAAg5JREFUeNrt3NFxwjAQRVHb4zboIX/0/5k/ekghpIDMMEkQSLvv3AYA6+7T'.
    'SsjaNgC57Ct+qcv1dp/xuV+fHzsBQgc/VZbd4GfLdJgFs8XfPYTsJJAA4RAgfCog'.
    'gAQAAUAAEACBnMlrZnsPhQV4t0RdZTk3/FuW0VLYCSxEh8EnQKN+hgBFq3/2uQIC'.
    'hFY+ASZX/yoniggQPPgECI19+wATqn/VQ6RHxcqodiJ35e9b7lj4jIf5TPWvLuup'.
    'WrLxsIOr3yoABEifygigB8Do+b9SIysBJABSq18CgACJnT8BQIBXzP8EgFWA7l8C'.
    'QAKofgkAAuj8CQA9QO/qr36ETQLoAZBa/QQIH3wCgADpF0WdCYM4Oq47vbxyJFSw'.
    '6+CaCvCXgSWBHuCHBKQIbAIv19s9eefPKkD1E0D1EwAEAAFQWwDXyTxPiwf4jo6+'.
    'q2ytftSrROicNC1/WJe7/AlAAgKQgAAkIMA6IvgzKJxufyIdBi0bCRAuFAEkAJJT'.
    'wDIwfFUgAcKxExieAhIgvB8gQLgEBLAMBAHQZllHgAGDnyQBAUwBSJ4KCDBIAreF'.
    'B0tQOS1OQ5w9HUgATSAIAAKAACAACAACgAAgAAgAAlTFC6ESgDQEAAFAABBAH0AA'.
    'EAA5ArhJXAIgXQApIAFAABAAuQLoAyQACR4Q9WB+s82bJktcZTySQFIAAAAAAAAA'.
    '6Mk3+3LoD93S5XYAAAAASUVORK5CYII=';

  $cifry[7][6] = 
    'iVBORw0KGgoAAAANSUhEUgAAAIAAAACACAYAAADDPmHLAAAAAXNSR0IArs4c6QAA'.
    'AAZiS0dEAP8A/wD/oL2nkwAAAAlwSFlzAAALEwAACxMBAJqcGAAAAAd0SU1FB9oK'.
    'EQgIMUpwZjgAAAAdaVRYdENvbW1lbnQAAAAAAERtaXRyeSBZLiBLYXphcm92ddNE'.
    'LgAAAflJREFUeNrt3bu1wjAQRVFby23QAxn9h2T0QCHQgfksf0a6+6Qkz5qjOyM/'.
    'jKcJAAAkMluCOlxuj9fa58/7dSZAUMGPkGBOXcie2VKCRcGzWRSeAAofTFN8Aig+'.
    'AUAAu58AyLoHME1FbwTZ/ccUv7v7AAofeh9A0fdjsYj1WtiRf3c5Af5dvLOK3bu0'.
    'TgGOgXZNlfg/4zokQPjxlQCBgx8BFH8cAUaZ/gmg7xPg1x09wu4/+xpar4tSofgj'.
    '/NNK/zyx+BUkJkBw8Q2BTi4ESD+2EiAcM8CB/b/isVUChB37CKD3awGiXwLE73wC'.
    'hPd+AsAMkNz7JQAkQPrulwChkz8BQACYAXbp/719T1ECSACk7n4JAAIQAGYAZPZ/'.
    'CQACbEHPzygO9Stha/HtMfLBZ4BvevcnCSq8w0cL2HFw8xO0ZgBypAsgBSQAKQgA'.
    'Amyw+0c4WkoACYDU3U8AZAuQ/m6CWAHcC5AA8b0/XgApIAHidz8BQAACiGYCgABS'.
    'gABIFIwAEgDJ7YUAEgDJw2VTLAmAYKGaokkAEAAEQGQraYonAUAAEACRLaQpogQA'.
    'AZCaHASQACAACIDMkwMBJAAIAAKAACAACAACgAAgAAgAAoAAIAAIAAKAACAACAAC'.
    'gAAgAAiArhn+e/Dfvhso9WniiIv+JIFHyQEAQBpvNiDMnryRvG4AAAAASUVORK5C'.
    'YII=';

  $cifry[7][7] = 
    'iVBORw0KGgoAAAANSUhEUgAAAIAAAACACAYAAADDPmHLAAAAAXNSR0IArs4c6QAA'.
    'AAZiS0dEAP8A/wD/oL2nkwAAAAlwSFlzAAALEwAACxMBAJqcGAAAAAd0SU1FB9oK'.
    'EQgIFAF0sn8AAAAdaVRYdENvbW1lbnQAAAAAAERtaXRyeSBZLiBLYXphcm92ddNE'.
    'LgAAAgNJREFUeNrt3UtuwzAMRVEr6Da6h8y6/2Fm3UMWko5bFEE+skTrnTsNECDm'.
    'JSnRir1tAAAAiKO5BPP4/Pq+3fv8ejk3AoQGf5QEBCgc/BESnIQjGwIUz34CgACy'.
    'nwCCTwAQAARAXz5cgqye/5fmItVm71HwSfCzaYKfm/0WgSAAAUAAZPZ/AoAAydlP'.
    'AMybBJoFzM/+qRVg9A9FsQqwOq9WuJgKIPh2ASjQFglgDoDU7CeA7a1dQOLKXwUA'.
    'AUAAWANk938VACpAcvarAIH7fgIIPgEEnwDTqXIghgDhECA4+wkAc4DRC8Bqh2FV'.
    'gNDSTwDBJwA8JGpo/+/x/b0riUVgMQFGtxMCHCz4vSWwBgiHAAQAAXCo/k8AEAAE'.
    'gDlAZv/vOQhSAQ6GUXBo9u91N9HNoJBMj64Ar76rd4/sr3YuoAn4mpkdKUD1aV3F'.
    'U0FN4HODv4QAsj5YAL3+fQyCgoNPgPDgEyA8+AQAAZKznwAgQHL2H16AZy/89XJu'.
    '3lX0m4hR8H9B7zlEOrJUsqGDFFqA/m8RCALIfgLk9X8CgAAgAAgAAtgBEAAEAAFA'.
    'ABAABAABQAAQAAQAAUCAgqQeBiEACJCc/QQAAQgAAuA5VvqDKQFUAKRmPwFAAAKA'.
    'ACAACAACgAAgAAgAAoAAIAAIgJXxrODt8YOhKz5pnAAPSrDqY+Z/AD5PvamaK/+U'.
    'AAAAAElFTkSuQmCC';

  $cifry[7][8] = 
    'iVBORw0KGgoAAAANSUhEUgAAAIAAAACACAYAAADDPmHLAAAAAXNSR0IArs4c6QAA'.
    'AAZiS0dEAP8A/wD/oL2nkwAAAAlwSFlzAAALEwAACxMBAJqcGAAAAAd0SU1FB9oK'.
    'EQgIKsAVr9QAAAAdaVRYdENvbW1lbnQAAAAAAERtaXRyeSBZLiBLYXphcm92ddNE'.
    'LgAAAiVJREFUeNrt3TFSwzAURdHI422wh3Tsv6RjD1lIaCmYwYrt6Fvv3JoZAu/+'.
    'Z0nYuN1wOh+f388tX/f4urd3f7Ymnhrhj5KAAIXCHyHBIqZ5xCFAaIgEwMuspooA'.
    'Qg5mEb4GELwGEH4qTfj1cBCE+S8Bpt8aAKlrANNf4/qvAcLDL3MOIPj7sN3Y6pc2'.
    '9vI2+udYBW4XIHwCgAAgAAgAAoAAIAAIAAKAACAACAACgAAgAAgAAoAAO+i5w8fd'.
    'QJM2wJZghX8+Q28KFbA1AAgAAoAAIAAIAAKAACAACAACgAAgAAgAAoAAIAAIAAKA'.
    'ABW54j/BJoAGQOr0EwDeHj5y+is8F6EBQqufAIOp8lQUAYKnnwDh4RMgPHy7gEHh'.
    'V3oqek0NyqPpEzdAz5T2irC3AaqJ15LDfyWYPQJUbB2LwF/B/hfujK+81QAbp3W2'.
    '6tcAHSLN/LJrDfAGKu84NEBw+AQID58A4eFPKYATPg1AguRdQNWdQVUpYyal2vaw'.
    'ihBxVVlJhAoSNOFnS+ASEC6BRWC4BE342RIsws/GUTABTD8BhN91nZ7pqNkNITsX'.
    'Zkd+vxFiNeEfG8jV7hpuwj8vjN7PZBs42X78Cq/Ibab//AAqP6ZGgFv2DSSL6XcO'.
    '4HpPABDA9BNA+AQQPgGETwAQAAQAAUAAEAAEAAFAgGL4U7AGQKoApl8DIFUA068B'.
    'cBAtafr9KVgDIFUA068BkCqA6dcAIAD+4gcQAfCzWKNWQgAAAABJRU5ErkJggg==';

  $cifry[8][0] = 
    'iVBORw0KGgoAAAANSUhEUgAAAIAAAACACAYAAADDPmHLAAAAAXNSR0IArs4c6QAA'.
    'AAZiS0dEAP8A/wD/oL2nkwAAAAlwSFlzAAALEwAACxMBAJqcGAAAAAd0SU1FB9oK'.
    'EQgJA5u8BvkAAAAdaVRYdENvbW1lbnQAAAAAAERtaXRyeSBZLiBLYXphcm92ddNE'.
    'LgAAAnZJREFUeNrt3UFWAjEQRVHTx22wB2fsf+iMPbgQHHp0gihJivr3LYAD/V8q'.
    'le4OeXkBAAAAAAAAAABAW4ZLcJvT+XKd8bkf72+DAGGhV5NgCHk/OyUYwq/BLgmG'.
    '8LMlOIRvFSD84CpwGAPZkh4ubDaH8PUAwl8wT9/7PVb1Aq/GQM31+SoIsCjwj/e3'.
    'UXEqsgrQBCKl3BNgc/gVBSOAKQAEAAFAABAABAABQAAQAAQAAUAAEAAEAAHQD+8E'.
    'LqLqq+kqQHD4BJgc0ul8uVbflGIKCBrtBPhFeCt2/VRiGJXf+a0AM0Nf+fawHuAP'.
    'wXYJXwW4M4jZpX7HvoFhZNdg16YRTWBo8HoA4asAycGrAMLPagJ3NYLVt5ybAgJD'.
    'twwMDz1OAOGHCtD5oAcCGPUE2N3pd5BgCF0PIHirAOGnijCE/xXaIz/3WUSIF+Bn'.
    'ULPkIkAxAW4Fk1INhvDXTzUE2BR+pVe+q8gQI8B/L3iVk0cIsPEid5Sg9RtBj764'.
    'M8LafT9jdB39s0dWlwbxMPJ7ru9NAYtEe5QIu6YCAoRXg0MY2RJ4K3iyBNX3JJoC'.
    'wivDUwiQcvjzjt95uCjZv9cUEA4BCKDhSm4ED+GrAAgWlAAqAAgAAoAAIAAI8KSk'.
    'PTMgAAkIAAKguwDu8asA+oB0Ae6tAiTQA5BAE4hWAqSd50cAEEAVIAAJCEACAjSQ'.
    'oKqAmsBwnv4++8o/jlz1PZ0e3qg8n86Xa+X+w4ERE0ffX7/XygowjOJaDefqx9+t'.
    'nrV3WOqtFsDuYKsATV2ywM4NDK9eTg4Nn7raz5mVJXBeQLAUTgwJlaDaSsWyaaEU'.
    'Do0KlsE9CgD1+AQ6kmTdAE6kfgAAAABJRU5ErkJggg==';

  $cifry[8][1] = 
    'iVBORw0KGgoAAAANSUhEUgAAAIAAAACACAYAAADDPmHLAAAAAXNSR0IArs4c6QAA'.
    'AAZiS0dEAP8A/wD/oL2nkwAAAAlwSFlzAAALEwAACxMBAJqcGAAAAAd0SU1FB9oK'.
    'EQgPCS0zSGEAAAAdaVRYdENvbW1lbnQAAAAAAERtaXRyeSBZLiBLYXphcm92ddNE'.
    'LgAAAoZJREFUeNrt3U1OAzEQROHYmmtwB3bcf8mOO3AQWCEhhUhokpm4u753gEBS'.
    'z9V2fmYuFwAAAABAEKPzk3t5+/g6+m98vr8OAoSG30GMIfxsCYbwsyUYws+WYArf'.
    'KUDwwS2wCT6bKXwCCD+YLe0J3zuLuwm4CX//Y3SQodQIUP/BAtwbfvUPbTSA8AlQ'.
    'NfyVBdyErgEQvCGdwtEAIABSG4YA4RvS2fWFtA9o2AArSNBNrPYjQBPYAzxNpgpv'.
    'SJUTYM+LqgU0AAluMNIC3VvLHeu/dAOcFWTHs3+bEXCWBJ3HR4uPTY/6ttCex632'.
    'UbRj4I2gUzaNLQR4xKr7HXjSiaFNAzxagu6bv1Z7gFWqmwDBElT9HqJrBAWH7xQQ'.
    'Hr4REBx82wbwoY8RgNQRcNbq7/TLI1cJ0wDCT1399gDh4RPAmHGhSJtA4XsfAAQA'.
    'AUAA8z+OIfzsk4ARYATACFD/sWNAAzSc6wQQfk8BVjv6uV9Aw9Wf1gRT+NeBJ0kw'.
    'hZ89BmLuGGLT9zdbWviCLyLAEdUq/GtG99D3ht/1qmBLCrBS8GkSjI6hP2uXX1GA'.
    'rWP4Zv3CAqxa96l4KzhcKgIU29cQAARYuaqrtYArhYZLMKuHdUb4nTeC04rNZtkg'.
    '/lujZ8rU8V1BpwCnACSPLgJoABAABAABQAAQAAQAAUAAEAAEAAFAABAABEin4/WJ'.
    'CaABQABE1j8BDqLSdwenlZm7+jVA+OonAHoIcGRFd7830bICrFClCVcJc/v40JX/'.
    'w+gW7L2r8J7gXScweAZX/d2gU4BTgJWVTJkXd+VRUFlSIyC8oUr986u1QIfx5M6h'.
    '4fsSdw8P35SWfTJnS9D1NFL+Sbn8PAAAu/gGU0lVe6VLzgUAAAAASUVORK5CYII=';

  $cifry[8][2] = 
    'iVBORw0KGgoAAAANSUhEUgAAAIAAAACACAYAAADDPmHLAAAAAXNSR0IArs4c6QAA'.
    'AAZiS0dEAP8A/wD/oL2nkwAAAAlwSFlzAAALEwAACxMBAJqcGAAAAAd0SU1FB9oK'.
    'EQgQBnDWW24AAAAdaVRYdENvbW1lbnQAAAAAAERtaXRyeSBZLiBLYXphcm92ddNE'.
    'LgAAApVJREFUeNrt3U2WmzAQRWGLwzayB8+y/6Fn2YMXkkxzTqe7DQFcpffduX/g'.
    'XZUkjIvbDQAAAAAAAAAAANMy0g74x89fv/e+9vm4DwKEhT67BEPw2RIMoWeLMISf'.
    'LcEQfrYEi/CzWYRPAARLOJz47LXAmh7+Z6GlTC0jMfxXR+rWz+lYAcbsI/yIkF79'.
    'LgQoGv7/BrPl+3STYBG+baBVPQHm5B2jv5vEy6yj/8jwZ55GXAk0BSB5GlhmPGFn'.
    'lOxZp4GlW/jPx318FYZt3zZGJwEqhDvbRaGl0+iHRSBSBahSTmdbY6gAKoDRTwAQ'.
    'AAQAAUAARLGmHOhVdxITYJLAP3s/F4IahHnmbwyz/X6xGPkWgW/j6HIqfBUAqQJU'.
    'HP0dKtIyQ8CVT3R1CXQIMQUgmbXbaP9753B229eE6vJ2AZ6P+9hzove8xo0lHxlV'.
    'RnaV6w5p7WVjfgz6VwAWkIU6hMweRtUKYBcQLvhihLgOAAKoAqmVSwUIn7ZWMWav'.
    'V0odSOWt4Fehd+4ZoAKE704IEL4VLSOAFrF2AcInwGvhVusStuUzqy10V6NcBQAB'.
    'QAAQAARA2OKVAAfQ+XY2ApgCQAAQAHkLQAKAAAQAAUAAEAAEwGa6/6uZACoAUkc/'.
    'AS5EgwioAMo/ASLDr3wLOwFMASAAIss/AUCAxL0/AUCA5L1/awG6hNDl7+sqgClA'.
    'KU4t/7db4z6BV5TYvaF36l6iTVzISG8hwN7nBwl9kingqmngzMC7Na+K2wV4TtBE'.
    'FeC7EXd12B1b10376Fjhh1SAKotXU0CoBN07lpb98h0k8MiYMGbsT1z6gCpUgdmb'.
    'Upc/uHdIkNSJ3BQQGHqrCnB2FUh/7kCbgz9CAg+ZaCzAXgmEDgAA8JE/qzlNxSkh'.
    'pqUAAAAASUVORK5CYII=';

  $cifry[8][3] = 
    'iVBORw0KGgoAAAANSUhEUgAAAIAAAACACAYAAADDPmHLAAAAAXNSR0IArs4c6QAA'.
    'AAZiS0dEAP8A/wD/oL2nkwAAAAlwSFlzAAALEwAACxMBAJqcGAAAAAd0SU1FB9oK'.
    'EQgQKkION40AAAAdaVRYdENvbW1lbnQAAAAAAERtaXRyeSBZLiBLYXphcm92ddNE'.
    'LgAAAuFJREFUeNrt3UF21EAMhOG031yDO7Dj/kt23IGDwIoNj8AEtz2S6qt9kpnU'.
    'r5Labrvf3ogoV2v6F/z05duPK37v96+fFwDCTJ8IwWJ6NgSL+dkgLOZnQ7CYnw3B'.
    'YSE0H8qRCVD9H90lCRbzsyE4mJ+tR6L5H63MydA9Ukg/E8e/fnYiCGty9V/Rgz/y'.
    'OcwA4UnSITEORmUPrQfz567xIwGYZA4ACACSBgAEAAIAAYAAQAAgABAACAB/lB1D'.
    'EgBoAGD+s3qwM7u9SIDw2WJNNWHn3boz5le/aygBgs0HQLj57VrAGVPufhgEAEWH'.
    'sWeMSTE/sgX8y9wk81smwK4l2XtG3dViJEDBKkszv20C7IzrtMgHQIMk0gKC2xAA'.
    'CACq3wxQdg6Y9vCpV8UGmz8GgDtgmPrYuRkg2PyRCbArBVJeNDH2Sybcy98hm0JD'.
    'jdcCmG8VAAKrgJdcT5AAxQ1MSgKnhlkGAiAZBPcCwmEwBIYPiQAIh8B+gPB2ELUp'.
    'dPfRLzaFNq38XcZNaAcrrfqvMtDh0cUr/28GJbeEI8F8LWFAAtzV89PSYDwA/2tE'.
    'yray0ReCdpwWOr0lrKnVX6mvV04Cl4JvSoKqaeDgyPBVQvkWsOOCT7WlaKWWcKj8'.
    'mcs7M0BhCCq1AgCcgOAMCFUgKA1Ah3V0dwiOSRWZ+LcB0LwlvDoFygLQdbNFNwiO'.
    'KdXn82gBlAhA1WrrkgISIBwCAGgBBAACQFc5LFoCUDoAUkACgEALoFEAdLyzBoAC'.
    'EGhLw1pA6itcAdC86rq0oxYA3H3yd1LKjV0FGAif0+jzAn6H4K5K6wTfUtV7waj2'.
    'KNsoAK6urlc88fNqABwZ846B3hZuuPtQlXY9pkYCnIBu95tHLQObVM2ONKryPWLe'.
    'FAriYQB0BaHacOncQABkDW7MDwGgIiQVAYjeEmavQXgC3JkGVWEDwA0wVE4aAFwM'.
    'RfU2A4ALQTBjEBERERERERERUQ39BE+/xJKu5nphAAAAAElFTkSuQmCC';

  $cifry[8][4] = 
    'iVBORw0KGgoAAAANSUhEUgAAAIAAAACACAYAAADDPmHLAAAAAXNSR0IArs4c6QAA'.
    'AAZiS0dEAP8A/wD/oL2nkwAAAAlwSFlzAAALEwAACxMBAJqcGAAAAAd0SU1FB9oK'.
    'EQgRAm6grjYAAAAdaVRYdENvbW1lbnQAAAAAAERtaXRyeSBZLiBLYXphcm92ddNE'.
    'LgAAAtFJREFUeNrt3cGN2zAQheGl4TbSQ27p/5hbekghyTUIdg3DpsgZvu8vYNfQ'.
    '+zkcUhL18QEAAAAAAIAIhkvwmG8/fv2Z8Xd+//w+CBAWegcJhrCvCbuLBHfBZ3MT'.
    'viZQ+IupNA3cBa8CCD+4CtwFrwkUfjB34c8r3R2lG8KfO18/+//1AEXDr7pnrwcQ'.
    'vgpwVfiJwR9TAYQfXAHeCT89+OP3AYRPAOGnLQMFrwKAAPtGf8et4NvpwQr/8Arw'.
    'KGDhH9gEfnaxNXivM7qHv1uCV0Z/JWHHCeHvurDdw2/TA1ScY08I3z4ACJA8+gkQ'.
    'Hj4BQICrqb5H0WIZWOF5v9NKf1wFeEeik18yiZoCXgny1fC7bE/HHRDxf6Dp9xHi'.
    'm8BHIzzh/cKj7gXMbBJTnjgmQNiyr60AHSTo2E+0+8FVJejaTLb80dUk6LySOHYJ'.
    'tEqS7svIo9fAq1cOBAifLjSBJLAM1DDaCLJSaCTBEH62CI6JC5eAAF+Etfu8QQJs'.
    'DP/KalJNgiH8Pce/VhFhCH/+MbCdJIgW4J0AZsqwU4RIAWZd8BMkiBPgigvd+eOS'.
    'w+ivuf+wSoaop4KvvKiz//aqDS3vBk6WYKYIKyRoPQVUKf+de4Ob8HtOObOI/Xbw'.
    'Dgkq3qjSA4TjrODwKUEFCJfg+BdDKjdiFb4xqALoAc5e/kEFQKoAjpFXAZAqgNGv'.
    'AoAAIAAIAAKAACBAIapsaRNABQABEFn+CQACVOfqexoECC7/BAABkss/AcLLPwFA'.
    'gOTyT4Ci5X/l42wEMAWAADruWI4+IOJfCTwm/jlR3w3cIUH1o2x8Pj58GmonwLsj'.
    'ZFUoXXqQYTTXOuRx9RQQdU5g9Qqzo0dxTNwTAT4bTMelZ9xh0af2NrECnCCBD0YE'.
    'S7B7g8pn40KDP1aADhJU2pY+cifQvn94BahcDRwVGyyFT8cGy1B1WjJXXiiHXgQA'.
    'AABAPf4Cn8OlbPFKEWgAAAAASUVORK5CYII=';

  $cifry[8][5] = 
    'iVBORw0KGgoAAAANSUhEUgAAAIAAAACACAYAAADDPmHLAAAAAXNSR0IArs4c6QAA'.
    'AAZiS0dEAP8A/wD/oL2nkwAAAAlwSFlzAAALEwAACxMBAJqcGAAAAAd0SU1FB9oK'.
    'EQgSLwBSoYAAAAAdaVRYdENvbW1lbnQAAAAAAERtaXRyeSBZLiBLYXphcm92ddNE'.
    'LgAAAl9JREFUeNrt3UFOwzAQheHY6jW4Azvuv2THHTgILBAbpIJaNdgz7/sPUKmZ'.
    'f57HbpKOI4Cnl7ePMz///fV5VL02Q+GzJRiKny3BUPxsCeaBaAhAACTTbgZYuf5X'.
    'nAMkgCUAyVxcguuxvcNyIgEWrtn3ruWVxCGAGUD3d5noCQACgAAgwP/OCQQAAVDz'.
    'LIAAEgAEENUEAAGkAAGQdhYwFSo7JSRA+BJBgD+K3H0+IED4cNhSgEfcypWyM5AA'.
    'J3V+lV0DAcLPBAgQfmYwFSEbCRAuXvsu8ZoYCaD4yQlwdgpUnzUkQPigORXKLgDB'.
    'UkV1xqNmgU6JclHkbIbiZ6fAVHxDIAgAAoAAIAAIAAIg4gyAAOHFJ0B48Y/DUXC8'.
    'CP4wIlwCAoTLYAkIl2AofrYIdgFNRYxKgB0vuqeDJYFXxVa40Om3jEcnwHfxk98s'.
    'NlK7/1rRzyjaziljBviHYu2cBFORsyWQAOHDYdkveW9Hrf430N3EkgA3FK5jKkzd'.
    'X2fnQgCzgRlgl+7vcgIpAcKTwCtiFn3uLrNAKQE8Ai4BWqXLDkITQALo0OSBsIwA'.
    '1n8JsHVnrv6NgQAgQNXEWZkCBJAAupEAIAAyk6eEAM4AJIAUIAAIAAKAACBAr60Y'.
    'AUAAEAAEAAFAABAABAABQAAQAAQAAW7ALWQSgASWgC8JiPA7l4Qv+VMC9wuED4GS'.
    'wS6ACElLwL0DY9Vn/m9hVC9SF1bNJdMFMgPoEgKQgAAkiKT8hewyHBoCpYEESE6D'.
    'VSK37p4qQqxMsYj43FmE1UtY1Pq5kwi7zC4GqAViGFwB7MEn11opwDH/0KMAAAAA'.
    'SUVORK5CYII=';

  $cifry[8][6] = 
    'iVBORw0KGgoAAAANSUhEUgAAAIAAAACACAYAAADDPmHLAAAAAXNSR0IArs4c6QAA'.
    'AAZiS0dEAP8A/wD/oL2nkwAAAAlwSFlzAAALEwAACxMBAJqcGAAAAAd0SU1FB9oK'.
    'EQgTGzj9ZHQAAAAdaVRYdENvbW1lbnQAAAAAAERtaXRyeSBZLiBLYXphcm92ddNE'.
    'LgAAAlBJREFUeNrt3TFSw0AQRFGtStfgDs64f+iMO/ggkILLVWAJWZrp9zMCKHD/'.
    '7Z21hDVNAAAAAAAAAPozKv2yb+8fn1u+/3a9DJEXFGBr8IQoLMBe4ROhgACvCD9d'.
    'BAKECzGEny0CAcJFGMLPlmAIP1uCeUJLSUs2QLUXtkMTaIDwJli6hPF9NXaqaAKs'.
    'qOL7rwkRvgXcrpfhwk9TAZ4JlgjNtoC1YdoinALaHecIQAICgAAgAAgAAjxL6lGQ'.
    'ABoAyUdHAmgAJL8VvCSH4DJxoxtCXh18l7eO57TVb9UHzwDCDxZA+KEzwB7Bd7p0'.
    'XFaA30Kw4kMbYO/gu904Mnda/cIPHgKFHyTAfdjCD50BBL+dkRCk4ANmAOETQPgE'.
    'EH7JGeDIOSD9n0UXKz6bRfDZzMLPJuZTwoRfQIC9JBB+oS1AWGYAEhgCEXsMdCtX'.
    'cAMIP7ABhB7cAMIPFkD4GoBsqQIIRAOQ7iSMtCC8yxjaAJrgBA3gaaDBDfBf4Xvo'.
    'Q9EG2CrAo9D3+JkaoEj4AgwZAvcM2UAY9tAoFBPgr+FukSC9BXYXYO0LbGVrgJcJ'.
    '47Fx9nUNoAU8McTqJ0B2CxAABNACBIglcRBsK8DaFkiTQAOES0AAMwCSW4AAGgAE'.
    'cBIgAAkIQILAQTBmBiCBIRAEAAGQK4ATgQbA2QXwKDgNAAKAAOghgD1XA0QNmQQQ'.
    'PgFQh8UqJ4AQbQEgAAgAAuCO7m9kESA4/Gkq9FnBwtcAwq/cAFVaIO3i1WK9ZwZ/'.
    'SAOcsQXSL1cf8scfIYH7EgAA+MEX9bEr6cNR9ysAAAAASUVORK5CYII=';

  $cifry[8][7] = 
    'iVBORw0KGgoAAAANSUhEUgAAAIAAAACACAYAAADDPmHLAAAAAXNSR0IArs4c6QAA'.
    'AAZiS0dEAP8A/wD/oL2nkwAAAAlwSFlzAAALEwAACxMBAJqcGAAAAAd0SU1FB9oK'.
    'EQgVHYfEZscAAAAdaVRYdENvbW1lbnQAAAAAAERtaXRyeSBZLiBLYXphcm92ddNE'.
    'LgAAAr5JREFUeNrt3UFWwzAMhGGc12twB3bcf8mOO3CQsuU9FoTGaSzNpwPQkvk1'.
    'kuPaGi+h8fr+eZ/9N78+3ka15zAInw3BIHw2BIPw2RAM4mdDcCN8dgzCZ7vAIHw2'.
    'BBvxs2MjPgBEs7V9RA9w1Ru9Rz53ZXhuxO+d4e1KwNXidwNlI35m5pcrAbPFTxe+'.
    'lAMQnwMQHgCEtwogPgCID4B2S1EAFMn+jptPG/FzxVcCBACSsx8AAgDJ2Q+AJ4i/'.
    '+rsIAASLD4BiS1EAEB8AGj8AnCrgUfGrbUBxgGDxARBa9wEQXvcjAHAlDAeQ/Tti'.
    'dBfpr+xMbPw4APEB4G6BYABSNnpaAfDoA5fpHED2dwFghQff7bDJTdZnxyBobvbH'.
    'vwdIFx8A4eIDIFz8sgA49h3eBD6rGUwAreU/OAuMBAD0AOFlBgDhAYCL+gsAEA4A'.
    'YLIKKCFY54ZwED8bAlPDwiEYxM+GQBMY3hjaCwh3Ag4QHsbGhbtApAP4PUFxBziS'.
    '/T/Fd0YgvAeYIV71VcFIzf7ZIroihhPcAVAw+9Mbwy1BfN8xvATsze7EUrDJ/uxS'.
    'YHBkeCmwCggvBSaHhkOwPADVt3xXh8Dk0PCmcOuY/Xb7gh3gDPFdECHa9gEACHcB'.
    'AIS7AADCXWAjUDYEHMAyUCS7AAA4gEh2AQBwAAEAAQABAAGA2uGOQA4AAiVAxAPA'.
    'BTgACJSAcyHoAlj7HoATFAZg1oYLCAo7wEwIgPA7ymxpzhbvCFidDqyU6QFmPzxu'.
    'UMwBzhZtL2DdjquVWgWc+RD3CNvRNYyP/wdoHQ+rmh28U+iuPYN5ATtgO/o5KzuA'.
    'kTENHCsagAogACAYgtXPD7Q77boSBBUOj7S9+sRImXAArgahytGxqNu0TBgNB+AZ'.
    'EFQ7NBr3q2B3CIY7wJlOUBEu2fCSPTQKABNgUFaEEEIUjG8L4qTzuyUwXwAAAABJ'.
    'RU5ErkJggg==';

  $cifry[8][8] = 
    'iVBORw0KGgoAAAANSUhEUgAAAIAAAACACAYAAADDPmHLAAAAAXNSR0IArs4c6QAA'.
    'AAZiS0dEAP8A/wD/oL2nkwAAAAlwSFlzAAALEwAACxMBAJqcGAAAAAd0SU1FB9oK'.
    'EQgVI0ale2wAAAAdaVRYdENvbW1lbnQAAAAAAERtaXRyeSBZLiBLYXphcm92ddNE'.
    'LgAAAfhJREFUeNrt3btVBDEQBVHNnE2DHPDI38QjBwIBGwwMdhkk1a0E9uy86lZr'.
    'vmMAAAAAAAAA2J+j8CefXt4+rvqt99fngwDR8FcU4RB+W4TTKri/hMkOMNuBn7UT'.
    'nMK3CxB+uAucwm9jCCSA6icACKD6m9zqB+CeyXwH8c5y9a924YYAIIDqJwAI8H+D'.
    'HwFAANW/z7kHHSAcfk6AR1T/bmcddYD4tpMAhkAUWz8BQICrqn/mE08EiLZ+AlwU'.
    'vieDhK8DCJ8Awp+Um8gbwx4BLgp+tXsNboJvcwq/W/1JAVT+Vw5hdqvfNjAevl1A'.
    'OHgdQPgEqIdPgHj4ZoBw8ASIB0+AePBmAOEToB4+AeLhmwHCwesAwl9fAG/6uJ9l'.
    'D+AjLwWXRTrKwZNgMQH++gaQogSH8J0HEL5dAKqynQLRARCW7hSEDoCwfASIS7Dl'.
    '1cDve3lLSagDuEBkCSBFVYCfgv6tBF4TBx3AUkAA20ECgAAggDmAAOYAAugCBNAF'.
    'CKALEAAEAAHMAQQwBxAABAABLAMEAAHsBAgAAoAAIAAIAAI4F0AAEAAEwEz4enj8'.
    '3QG+Hq4DCJwAAjcE7hS68MO7AOHbBoIAIAAI8FfsdPFo+j8y21Dn8/GqfRBA8AQQ'.
    'vBlgizmg/mRQ7rNxngheVIB7RRA8AAAAAIwxxienFusYB+cPgQAAAABJRU5ErkJg'.
    'gg==';

  $cifry[9][0] = 
    'iVBORw0KGgoAAAANSUhEUgAAAIAAAACACAYAAADDPmHLAAAAAXNSR0IArs4c6QAA'.
    'AAZiS0dEAP8A/wD/oL2nkwAAAAlwSFlzAAALEwAACxMBAJqcGAAAAAd0SU1FB9oK'.
    'EQgVL08TN0cAAAAdaVRYdENvbW1lbnQAAAAAAERtaXRyeSBZLiBLYXphcm92ddNE'.
    'LgAAAj1JREFUeNrt3UFOwzAQhWGnyjV6B3bcf8mud+AgZYXEArVFioNn3vdfgDTv'.
    '99hjEmcMAAAAxLEl/ujr++0++298frxtBAgKvKoEm/CzJdiFns1F+AQQPgGETwDh'.
    '2wcQvjZQ8GFCbMnhHx3IK9e1mgSb8M+/rpUk2JKCn3nj/3p9q0iwG/HZ7J1DF34h'.
    'AWat6oW/uAAz2znhP+cifBVAyVcBhK8CFA5f8EXXAMLXBQieAEIngOAJIHSLQBCg'.
    'JpWfSSRAcPgECA+fAP8UfvQjYVUfneoYfokKsFqZ7fYSSomNoN9u+pkjqfObR2X/'.
    'F3CWFN1fO2t1PsDRUiS8c9i+C7i+3+5eHl2oC0gZWbqAYq1dKhcjgQDKoTXA+qv5'.
    '1ef0V6/ZGqBRZehQwco8EbRaVegyfZXZCHp0w8+Wo9PapcVO4M9AZsvQbeHabidw'.
    'ZkAdu5aWZwVrL4MrAAgAAoAAIAAIAAKAACAACAACgAAgAAgAAoAAIAABQAAQAAQA'.
    'AUAAEAAEAAFAABAABMAzfDACKoDRTwAQAI9Y8eAKAgSXfwKEj34CYDhM6YTyv/Kh'.
    'VSqALgAEgDUA8uZ/FQAEIACmlX8ChFPh0GoCmAJAAESWfwKAAARAbAtIgPDwCQAC'.
    'EACxLSAB4HmAoxeA1T5aqQJYAyCx/TMFTAi/4jeLVYDQkf/NLurM4E0BB4de9ZP1'.
    'u9DtAwg/dPRHCiD8YAGErw0EAYx+AgifAMLXBcSGbh/gBQk6hx4vwCMJUoIHxhhj'.
    'fAEr1gs5lwRbfwAAAABJRU5ErkJggg==';

  $cifry[9][1] = 
    'iVBORw0KGgoAAAANSUhEUgAAAIAAAACACAYAAADDPmHLAAAAAXNSR0IArs4c6QAA'.
    'AAZiS0dEAP8A/wD/oL2nkwAAAAlwSFlzAAALEwAACxMBAJqcGAAAAAd0SU1FB9oK'.
    'EQgVKaZwknIAAAAdaVRYdENvbW1lbnQAAAAAAERtaXRyeSBZLiBLYXphcm92ddNE'.
    'LgAAAm9JREFUeNrt3UFS6zAURFHLlW2wB2bsf8iMPbAQ/ogqJlT9ECvSe33uAiBR'.
    'X7UkY5vjAAAAAAAAAAAAQFtGhy/x8vbxdeXP+3x/HQQICz1VhiH8bBGG8LMlGMLP'.
    'loAA4SIMwWdLMISfLcIQfrYE54E4WSMa4J4ZmXwlcXQK/4qBv0qGKhKc3Wb+LsFV'.
    'WRJGl/BnzLgrQty9CU7h9z7ntz8FzA6puwSOgf8pwV9F2H0vcJr92W1wCn/+7925'.
    'BbYx+p5B2mEmrrpO0bIBql5GtQSEbww7iE6AcAnOhEF3Mvidm/Cz9y6WgAXh7yQO'.
    'AcJPLQRwDERyixBAA4AAiF0GCKABkNwCBNAAIAAIAAIgcCNIAA0AAqhKAuB6Ktwu'.
    'RoDwFjqrzRTLgAZAugCrWqBj+5RtgGeG8fL28dV16blV/vA/Q5m1497trSVtG+DR'.
    'AZsxQxM2nFs1wOf763j0gYtHREo8Ydy6fSGvlC++Caz+sGW1z38axOzPfRrMbE4z'.
    'KlvYW6WB9Z9Dwk8B3wNtpx+yBKh7AmwVwCOvjLUETAjjGUtB99NI6SuBjor2ACAA'.
    'CAACgAAgAAgAAoAAIMB6KtxkSgANgNWs/JsGATQAUtd/AoAABEBs/RMgPPzj2Oif'.
    'R6eGv/q2Ng0QOvMJIHxLwOrwd7irmQDB4RMgPHx7gAXs9jCLBnji7N/xSSYCBNY+'.
    'AYRPAOETYGrwFcIvK8BvwVwx4Fdd1avy6ProNiP/MvAzLuUSYLM6Tj7rlxdA+PNo'.
    '97Jowd+HS8HB4RMgPHxLQHDwGkD4joHp4ZcSYKUEnV9IWfaLzZTBG0gBAAAAAAAA'.
    'AI34B2DXQeLsGtvPAAAAAElFTkSuQmCC';

  $cifry[9][2] = 
    'iVBORw0KGgoAAAANSUhEUgAAAIAAAACACAYAAADDPmHLAAAAAXNSR0IArs4c6QAA'.
    'AAZiS0dEAP8A/wD/oL2nkwAAAAlwSFlzAAALEwAACxMBAJqcGAAAAAd0SU1FB9oK'.
    'EQgVNbJxzj0AAAAdaVRYdENvbW1lbnQAAAAAAERtaXRyeSBZLiBLYXphcm92ddNE'.
    'LgAAAlFJREFUeNrt3bFxwzAQRFGSwzbUgzP1HzpTDy5Ezh14LAskb3HvNyAK+3EE'.
    'MASwLAAAAACARqzdG+B2fzyv/P2vz4+VAA2DryLBKuzeEmzC780udAIIvjGb8FUA'.
    'wVsHEL5poODbhR8pwNHhXx0IAYROgDPC7xx6jABHhC/4yaaBQp+0Aozq/cIPFGBE'.
    '+IIPFUD4jQV4N3zBBwsg/MYCvBO+4MMFEP61xH4PIPzGAgh/HLvgCSD8wWOXJFE3'.
    'fWD8wDXpgxUCHDRrSZGAACdNWQnQpEHT/t+mcawDgADoWukiBEhfAKosgQrgFYDO'.
    'g1YCqAAggPLbsvxHVQALR5MJkDK1GyVeVYFjvwk8Q6DRoVWUPnYQeLs/nkf2qi6v'.
    'nOivgo/qYUeGX60KxO8LGNXQZ/V4AlwUwG8Nf3a5ryTBVHsDU6gkgJXARlPa8gIk'.
    'NuR/nrlSpZv2gIgrgn/luavIvlVt3MrVYKYdSntKL6tSFWbbnuao2MHh//XZqogU'.
    'eUzcz8Y7Q4hZN6ZOMQ08Mpzq45GWFeCs8UKH7ehTnhTqHIFmrwAQAAQAAUAAEAAE'.
    'AAFAABAABAABQAAQAAQAAUAAEAAEAAFAAAKAACAACAACgAAgAAgAAoAAIABeIPGk'.
    'UwKoAOjMrgl6ln4VQPjLsoQeFJkevvsChG8MoNwTQOheAb0Cr3iApQqw9L6WdhX6'.
    'OVQ9vnYVfN/wowVIKdvVD652Y0jj8CMFSL1RzDrA5KTeUUCAhqEToHnoBGgcOAGE'.
    'bhrYPfR4AUZJ4HYxAAAAAAAAAD34BrmcHcUHRalUAAAAAElFTkSuQmCC';

  $cifry[9][3] = 
    'iVBORw0KGgoAAAANSUhEUgAAAIAAAACACAYAAADDPmHLAAAAAXNSR0IArs4c6QAA'.
    'AAZiS0dEAP8A/wD/oL2nkwAAAAlwSFlzAAALEwAACxMBAJqcGAAAAAd0SU1FB9oK'.
    'EQgVO1XJ4zoAAAAdaVRYdENvbW1lbnQAAAAAAERtaXRyeSBZLiBLYXphcm92ddNE'.
    'LgAAAeRJREFUeNrt3bENAjEQRFH7RBv0QEb/IRk9UAhExAjOnOyd9xvgxHzvro3w'.
    'tQYAAAAAAAAAKE6f/QHP1/vzqM963C6dAGGhp0vQhZ8tQhd+tgRd+NkSdOFni7DZ'.
    'CNWRVAVQDVQAwhKABF9wWvGhR5Xgqn29bAV43C59ZP9NPPrVAkhAgFESVGghBPhT'.
    'eyGAlkAAEKD83nv1OUAFCG8DBAiXYPNlmwFAABAABAABQIC58Ru+CtDO1/uTCFqA'.
    'amAGIMFeSv01bPSJ4ii5Zj7p9OfQg5hVAtvAcLE3K8UQqFwSgAR2AfpnpNRLrbLV'.
    'RSBAsASztrQl+6z7BMIFmFmC1YZXk/YAyVbesTgJdA4AAoAAIAAIAAKAACAACAAC'.
    'gAAgAAgAAoAAIAAIAAKAACAACAACgAAgAAgAAoAAVUi5f5AAweETAARIXv0ECA+/'.
    'NRdE7A5/9evsVIAdVLjLkADB4RMgPHwCBA9/hsAfw692h/Em/Gy0AAIgtfxHzwDp'.
    'vf/NSfDZdOHnrv4oAYQfPAQq+4EzgNADWsC/Q054c5l3BgWHv9wMoKwHV4Ajw096'.
    'aaWj4ODwS+8CBK8CCJ8AwieA8OucA+zZCQi7QAUQYngF+LUqEKewAJ9EED4AAAAA'.
    'AAAAAAAAIJAXkovMdUyTaVkAAAAASUVORK5CYII=';

  $cifry[9][4] = 
    'iVBORw0KGgoAAAANSUhEUgAAAIAAAACACAYAAADDPmHLAAAAAXNSR0IArs4c6QAA'.
    'AAZiS0dEAP8A/wD/oL2nkwAAAAlwSFlzAAALEwAACxMBAJqcGAAAAAd0SU1FB9oK'.
    'EQgWMOk2aXEAAAAdaVRYdENvbW1lbnQAAAAAAERtaXRyeSBZLiBLYXphcm92ddNE'.
    'LgAAAiNJREFUeNrt3c1Rw0AQRGHNFmmQAzfyP/pGDgQCEWBKsqTanf7eHRu73/bs'.
    '+kfeNgAAAAAAAABAcyrpwb5/fv0c+bvvx0cRIDD4BAlK+NkilOCzJSjhZ0tQws8W'.
    'oQSeLUEJP1uCEn62BEP42VSyAH+t2Fduf7UWGKnmPwvqlRBXa6dKW/17wk147yBK'.
    'gKPBHLnPVSQYwu99zv+Pt+7hdw4vZgTMEv7e/2MF+dqeAq548vfe5gongtFx9av9'.
    '4Aa4Ovxuco1uq99jaCKA6tcA04bfSbRhDWgAqz94HzCEnz0GjIBwCQgQPgYIYBMI'.
    'AoAAyNwIEkADgAAgAAgAAoAAIAAIAAKAACAACAACgACT4oJRGgAdBfA1Lw1gDBAA'.
    'BAABjIFQATpek4cAmmAqSqDZx2CbwHDxCRAuwfQCeEVQA5AgeROYvim8Wv7hCTEC'.
    'rAojwC45VfQWK6mjCHe1nCq9UcAZR5cXgmwCQQAQAAQAAUAAEAAEAAFAABAABMAT'.
    'Vn8nkgDB4W+bt4NvC3/WTzFpgHA0QPDq1wChc58AMALuWv2zf4RdA1zICt9fIIAR'.
    'gLSdvwZQ/QQAAWLP/gRQ/wSw+gkAAmTXPwHghaCz5v+ql63RAPYAIADijn8EgE3g'.
    'WSt/5esWaoDA2ieA8I2As4Jf/bK1Jfzc8KNHgB+XCm6AM8LvcsXyEn5u+E4B4eET'.
    'IDx8AoAABIBjoJNA5vyPFWCvBJ1/pewXbx36Zh01n5QAAAAASUVORK5CYII=';

  $cifry[9][5] = 
    'iVBORw0KGgoAAAANSUhEUgAAAIAAAACACAYAAADDPmHLAAAAAXNSR0IArs4c6QAA'.
    'AAZiS0dEAP8A/wD/oL2nkwAAAAlwSFlzAAALEwAACxMBAJqcGAAAAAd0SU1FB9oK'.
    'EQgXAaHzWAoAAAAdaVRYdENvbW1lbnQAAAAAAERtaXRyeSBZLiBLYXphcm92ddNE'.
    'LgAAAjFJREFUeNrt3ctRA0EMRdFp16ThHNg5/yU750AgZgsbirLbM2q9cxMA/K7U'.
    '0nzMtgEAAAAAAAAAmjO6/mHX2/1xxM/5+vwYBAgMvosQQ/jZEgzhZ8sxhJ8twcUc'.
    'nC3sxYeZjQ5AANVPABBA9RMAgexpf/CzO3jXjrPshaBnApl5AWaWEGdfFBop4b/r'.
    'w54hwpkSEKBhd2o9BHYM3xYAAoAAIECt3Z8AwicACAACgAAgAAgAAoAAIMAqXG/3'.
    'h+cHdYBDbtOuKFrUEaATmAFKSnDm/YrIIdBcYAuY3g1WFWpPr4CfwSXeOnYdYEIV'.
    'v1L9Z0tHgBfDXH2WiHovYHaFvvp7VDhyol4NmynE6m8ELS9AhxZMgHARKgjQZgj0'.
    '9G94B1ixGzgCgkWo0rEi2qYbQOECVBPBV8X64DcCkIAAJDADRM4HlQQkwAlCECBc'.
    'BAKES2INhC0ABAABDIkEAAFUPwFAANVPABCgO9XuRBIgHPcCDjz/Kz6HoAOEDn86'.
    'wMHhV30KSQewBYAAiDv7zQAHhl/5KWQCBIfvCHgzK7x/oAOErX06QPjQRwCtnwAw'.
    'A8Sf/TpA+NmvAwTu/DqAytcB3hX+ql86oQOErX0E0PoJoPoJoPoJoPqjt4DEnZ8A'.
    'wieA8H+zCzubIeDc6l+uA6jo0A5QIfiuXzA9hJ8b/rYVvxCk5YcLAAKcTvd/LkGA'.
    '4PCXWwMFTwDBE0DwBBD4NFpeCBJwIwH+I4HAAQAAAAAAAOAvvgHP0hp2a+yzAwAA'.
    'AABJRU5ErkJggg==';

  $cifry[9][6] = 
    'iVBORw0KGgoAAAANSUhEUgAAAIAAAACACAYAAADDPmHLAAAAAXNSR0IArs4c6QAA'.
    'AAZiS0dEAP8A/wD/oL2nkwAAAAlwSFlzAAALEwAACxMBAJqcGAAAAAd0SU1FB9oK'.
    'EQgXB0iQ/T8AAAAdaVRYdENvbW1lbnQAAAAAAERtaXRyeSBZLiBLYXphcm92ddNE'.
    'LgAAAnxJREFUeNrt3cF1wjAQhGEvjzbSA7f0f+SWHigkOeUKj4dtSTvfFEAcz7+z'.
    'WtmIbSMiIqJEVdd/7Ov753ePz3ncbwWAQONTQChmZ4NQjM8GoRidDUIxPxuCYn42'.
    'BMX8bAiK+dkQFPOzIbhsFA0zAMIhiGoBr6L5iGuYvR1cO1fhuzf/cb9Vp7VIbALs'.
    'UXWzXY8EOPlG/39W90RYOgHOqqw9IJg1BUwBzeZ6ABgLASAFAACC1QBY5eZ2g2Dp'.
    'BBjVVztBoAWECwDhKQAACWC+Tr5WCSAB1u+raY9wJQABQAoAgDoCsNJ83WEvQAKE'.
    't6pLp8qyDpAAUqALAN1TYJbrlABagIhNngQkgAToOWebCCTAaRCs3AamB+DTmwuC'.
    '56qUat7ToE7H0Lb+eniXWV0CnJwCSW2jVOW665vIKaD78e0AIABIAQAQAKQAAAgA'.
    'UgAAIAAACI5Sqxs3egv33b9vK3gxCJ4ZNvNBl1EArASdZwFkEUgAIGsA/V8CmDgk'.
    'QIr5M21cSYDQygcA8wGQbr41wADjZ3tw5YshYRUvAQaaP+NjawAEm28RGBr7EuBk'.
    '82d+YwkAweabAg7UKu8pSoADqn+ll1QtAkMrXwIcUP0rvp5uDRBqvATY5jp4CgDM'.
    'B8AqPd9vB1vwtVExP7f6YxJA5QcnQPqcH7sPoOpDEyD1qV48AKPOBQBA86jvfvxM'.
    'MT/X/OUA8GtgoQAwPgyAUSNc4nFzxfxc86cCYOTGTfJBk5VsfLr5QwCYZYvW8bID'.
    'AJjBfMYPAkDUz6mI9wGYHwwA84MBYP5rXRkNAMYDgOnWAGQfYPa9ANW/OACfQMB8'.
    'IiIiIiIiIiIiIiIiIqJ39AfJ7F3bs7lnMQAAAABJRU5ErkJggg==';

  $cifry[9][7] = 
    'iVBORw0KGgoAAAANSUhEUgAAAIAAAACACAYAAADDPmHLAAAAAXNSR0IArs4c6QAA'.
    'AAZiS0dEAP8A/wD/oL2nkwAAAAlwSFlzAAALEwAACxMBAJqcGAAAAAd0SU1FB9oK'.
    'EQgEHzoTJPsAAAAdaVRYdENvbW1lbnQAAAAAAERtaXRyeSBZLiBLYXphcm92ddNE'.
    'LgAAAixJREFUeNrt3cFVwzAQRVHJJ23QQ3b0v8yOHlII7FlxDpY8439fA4DmzddI'.
    'xPEYAAAAAIAgZuVf7uPz6/uKn/t+PScBAgufKMJU+GwJpuJnS/BQeEOg4genwVT8'.
    'bBGm4meLMBU/W4LDGJQt9bRQ2UlwKL5jYDsBzuq0FVJ2S4HZqfgrFvdsCQiwYJFX'.
    'L2qyBOVPATsWM+nfv60S4IrCVN6uohLgqgV8v57TB0Iu7qpKBfhPInQQyU1gOARY'.
    '2MUdLr8IEH5KIEC4BAQwA6DDvQIBQABzAAFAABDAIEgAcwABQAAQwBxAAHPAzQTw'.
    'DIEEQLoAUkACmAMIAAKgvwDJT95U52EJag23u5tl2xYgBf52stl9+jmqL1jicXDn'.
    '33x0WRD3AmYAl0OOgSAA+grgJCABkC6AFJAAKNQkvi4+uPiXCkCCGltiif04SYRq'.
    'M5C3hoUPwN4bSADsErWiAI6B7gFAABAABAABQAAQAAQAAUAAEAAEAAHwm7v8y5oA'.
    'EgCp3U8AEIAAiI1/Amyk6iNxPhS6ofsrPw8pAcwASNz7CQACgACx0z8BFJ8A6QMg'.
    'AcK7nwAgQHL8EyC8+AQI3/8JAAIkdz8B4PMAZw6AHb8HWQKETv8EUHwCwAxwWvd3'.
    'fQ+CBHAPgMS93xZwYvE7vwbH+3yDi28LQHYCpHe/BAABkrufAOHFj54B7vikrwSA'.
    'BND9EmDp0Y8ABj8CgAAgAAgAAoAATgCNcREUXPwxxvgBW/P6/2tJZNUAAAAASUVO'.
    'RK5CYII=';

  $cifry[9][8] = 
    'iVBORw0KGgoAAAANSUhEUgAAAIAAAACACAYAAADDPmHLAAAAAXNSR0IArs4c6QAA'.
    'AAZiS0dEAP8A/wD/oL2nkwAAAAlwSFlzAAALEwAACxMBAJqcGAAAAAd0SU1FB9oK'.
    'EQgEFdrGzeUAAAAdaVRYdENvbW1lbnQAAAAAAERtaXRyeSBZLiBLYXphcm92ddNE'.
    'LgAAAqtJREFUeNrt3TFyGzEQRFGBxWvoDsx0/5CZ7sCDyLkDl23uLmam388tk+iP'.
    'ARa1GH58AAAAAAAAAAAAAACAaaxJX+bz6/tnx//7ej4WAYICnyTGEny2BEvw2RLc'.
    'BW8TKPzgKrAEny3BXfCWAOEHV4El/GwJlvCzJbhZBbOlv08eiKNn2cRKdZ84u84q'.
    'r7//3QlCrEmzf8e6+s53qLAPWBPC3z2QnSVYws+WoO1TwOv5WJWep7u+FHLvNvs7'.
    'v31TEecABPB4l3xGcBO+ClB+llQPv/OBkD2ACmD2p85+FSA8/PIC2PWrAMInAAiA'.
    'PAEc/KgA1n8CgABmPwFAALOfAMIngPAJIHwCgAAYL8Du8ptyZb10BdgVQlK/gvJL'.
    'wNVhpDWraLEH0BnUJvASCRKvrbV6CjhTgtQ7i5cJcNQAnSFBcm/ClucARwZ2xN/q'.
    '/PbSpQIcOVCfX98/74T37r+fQvsOIf8j29kdSv717++sICN6BP3t4F7VlKqTANva'.
    'xL2ej9Xh0c4msMmewGdv+hTQcSAn3Vm4GdDc8EudA3QY2Im3lW4GODf8cgJUHOhq'.
    'DSnHC1BJgoQLqqVvB+8MIOV2cvnfC9jRoz/panq7H4w44uxd8I0F+FNwU1vREUCQ'.
    'eZtAEAAEAAGa0e39AwKoAEid/QQAAQiAaJycbV7/d59eqgAqAFJnvwoAAiQ++xNA'.
    '+ATYTZV3FwhgD4DU2U+A8PXfOcCG8Ku9t0iA4PAtASAAARCNPUDw+q8CBD72qQAb'.
    'wq98ZW11HfSrBnV6F/HVfcadNcApV85vncM/a232m0HBG7SzG0lbAkJmX5deBR4D'.
    'wyFA8OwnQHj4BAAB0ml/EKT8DxSgowRdW9SV/tAdJOjem7D8h68qwZSmlC2+RDUJ'.
    'JnUkbfVFdougFS0AAJjCLymZbbEa08dPAAAAAElFTkSuQmCC';

  return $cifry;
}
?>