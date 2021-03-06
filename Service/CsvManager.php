<?php
namespace Idk\LegoBundle\Service;

use Symfony\Component\HttpFoundation\Response;


class CsvManager{

    private $em;
    private $separator;
    private $enclosure;
    private $path;

    public function __construct($em,$separator = ',',$enclosure = '"') {
        $this->em = $em;
        $this->separator = $separator;
        $this->enclosure = $enclosure;
        $this->path = null;
    }

    public function setPath($path){
        $this->path = $path;
    }

    public function arrayToCsvResponse($data,$name = 'export.csv'){
        $content = $this->arrayToCsv($data,$name);
        return new Response($content, 200, array(
            'Content-Type' => 'application/force-download',
            'Content-Disposition' => 'attachment; filename="'.$name.'"'
        ));
    }

    public function arrayToCsv($data,$name = 'export.csv'){
        if($this->path) {
            $handle = fopen($this->path . '/' . $name, 'a+');
        }else {
            $handle = tmpfile();
        }
        foreach ($data as $line) {
            fputcsv($handle, $line,$this->separator,$this->enclosure);
        }
        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);
        return $content;
    }

    public function readCsv($columns = array()){
        $path = $this->path;
        $row = 1;
        $donnes = array();
        $isUtf = $this->isUtf8($path);
        if (($handle = fopen($path, "r")) !== FALSE) {
            while (($data = fgetcsv($handle, 0, $this->separator)) !== FALSE) {
                $num = count($data);
                if($row == 1 && !count($columns)){
                    for ($c=0; $c < $num; $c++) {
                        $columns[trim($c)] = trim(($isUtf)? $data[$c]:utf8_encode($data[$c]));
                    }
                } else {
                    $donne = array();
                    for ($c=0; $c < $num; $c++) {
                        $donne[trim($columns[$c])] = trim(($isUtf)? $data[$c]:utf8_encode($data[$c]));
                    }
                    $donnes[] = $donne;
                }
                $row++; 
            }
            fclose($handle);
        }
        return $donnes;
    }

    public function isUtf8($path){
        //TODO
        return false;
    }

}

        

        
        
        
        