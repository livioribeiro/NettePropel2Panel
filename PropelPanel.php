<?php

namespace Addons\Diagnostics;

use Monolog\Handler\HandlerInterface,
    Monolog\Handler\AbstractHandler,
    Nette,
    Nette\Database\Helpers,
    Nette\Utils\Strings,
    Propel\Runtime\Propel;

class PropelPanel extends AbstractHandler implements Nette\Diagnostics\IBarPanel, HandlerInterface {
    /** @var int */
    public $maxQueries = 100;

    /** @var int logged time */
    private $totalTime = 0;

    /** @var int */
    private $count = 0;

    /** @var array */
    private $queries = array();

    /** @var string */
    public $name;

    /** @var bool|string explain queries? */
    public $explain = TRUE;

    /** @var bool */
    public $disabled = FALSE;

    public function __construct($name = "default") {
        $this->name = $name;
    }

    public function logQuery($message) {
        if ($this->disabled) {
			return;
		}
        
        list($time, $memory, $query) = explode('|', $message);
        
        $query = trim($query);
        
        if (Strings::startsWith($query, "EXPLAIN")) {
            return;
        }
        
        $this->count++;
        $time = trim($time, ' SLOW Time: ms');
        $time = floatval($time);
        $this->totalTime += $time;
        
        $memory = trim($memory, 'Memory: ');
        
        $this->queries[] = [
            'time' => $time,
            'memory' => trim($memory),
            'query' => $query
        ];
    }

    public function getTab() {
        return '<span title="Datasource: ' . htmlSpecialChars($this->name) . '">'
                . '<img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABmJLR0QAAAAAAAD5Q7t/AAAACXBIWXMAAAb2AAAG9gEMFeMTAAAAB3RJTUUH3gIGFCwJ4IkD5QAAAapJREFUOMudk89qE2EUxX/ffIma0MRgjCUVSU1LNo0bNSBCoU/kE/gKPoL4ECJF/LOwiohQlSKCTYm60DYhxknSmU5m8h0XLUqr0bRndRfnnHsPnIukOUmPdDwkkp5IqnIwnBRvjSQxLRTjRl00HmCzC4BHaiqdC3FBk/jHC+L2fXB75K49BPM/A7dH4r8m7q4y6jyAcYiXWSBTvQ1mX/r3CEpIes+Je09xQQtsjlT+Kl6mgs1cxssu/qIeMRBu1MYNP+Di7xibxcsu4rldTLgOg5dQvgWZ+j5bOhrBYGwOL3cFmy5ioha074L/DML3kF+BdPk325gJEaIv8PUO+I8hVYDcDcgvw8xNSF84vPKQQdKDzj0INmCmAWdX4HSF4XBAFCWcK17EGDPBYNyH8COcmYfUeZwbs73dYmvrHb3eN2q1BrXadTzP/uMCIE5GdNqf2dx8Q7/fxdoU5XKVen2ZdPrUH2nNQSVJkhG+36XZXCcIfEqlS8zOzlMszmHt5LoYSUkUhXZn5xNRtEupVKFQKE1dbiStRlFw0md6haQlSWuS3DHfeU1S4ycDPKz+zZXMvQAAAABJRU5ErkJggg==" />'
                . $this->count . ' ' . ($this->count === 1 ? 'query' : 'queries')
                . ($this->totalTime ? ' / ' . sprintf('%0.1f', $this->totalTime) . ' ms' : '')
                . '</span>';
    }
    
    public function getPanel() {
        $this->disabled = TRUE;
        $s = '';
        foreach ($this->queries as $query) {
            $time = $query['time'];
            $memory = $query['memory'];
            $sql = $query['query'];

            $explain = NULL; // EXPLAIN is called here to work SELECT FOUND_ROWS()
            if ($this->explain && preg_match('#\s*\(?\s*SELECT\s#iA', $sql)) {
                try {
                    $con = Propel::getConnection();
                    $cmd = is_string($this->explain) ? $this->explain : 'EXPLAIN';
                    
                    $explain = array();
                    $result = $con->query("$cmd $sql");
                    
                    while ($row = $result->fetch()) {
                        $explain[] = $row;
                    }
                } catch (\PDOException $e) {
                    
                }
            }

            $s .= '<tr><td>' . $memory . '</td><td>';
            $s .= sprintf('%0.3f', $time);
            
            if ($explain) {
                static $counter;
                $counter++;
                $s .= "<br /><a class='nette-toggle-collapsed' href='#nette-DbConnectionPanel-row-$counter'>explain</a>";
            }

            $s .= '</td><td class="nette-DbConnectionPanel-sql">' . Helpers::dumpSql($sql);
            if ($explain) {
                $s .= "<table id='nette-DbConnectionPanel-row-$counter' class='nette-collapsed'><tr>";
                foreach ($explain[0] as $col => $foo) {
                    $s .= '<th>' . htmlSpecialChars($col) . '</th>';
                }
                $s .= "</tr>";
                foreach ($explain as $row) {
                    $s .= "<tr>";
                    foreach ($row as $col) {
                        $s .= '<td>' . htmlSpecialChars($col) . '</td>';
                    }
                    $s .= "</tr>";
                }
                $s .= "</table>";
            }
        }
        
        return $this->count ?
                '<style class="nette-debug"> #nette-debug td.nette-DbConnectionPanel-sql { background: white !important }'
                . ' #nette-debug .nette-DbConnectionPanel-source { color: #BBB !important } </style>'
                . ' <h1 title="' . $this->name . '">Queries: ' . $this->count
                . ($this->totalTime ? ', time: ' . sprintf('%0.3f', $this->totalTime) . ' ms' : '') . ', ' . htmlSpecialChars($this->name) . '</h1>'
                . '<div class="nette-inner nette-DbConnectionPanel">'
                . '<table>'
				.   '<tr><th>Memory</th><th>Time&nbsp;ms</th><th>SQL Query</th></tr>'. $s
                . '</table>'
                . (count($this->queries) < $this->count ? '<p>...and more</p>' : '')
                . '</div>' : '';
    }

    public function handle(array $record) {
        $this->logQuery($record['message']);
    }

}
