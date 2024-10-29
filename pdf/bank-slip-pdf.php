<?php

class PDFBankSlip extends \Fpdf\Fpdf{
    var $title;
    var $headers;

    var $line_y = 0;
    var $line_h = 7;

    function __construct($title='', $headers=[]){
        parent::__construct('P', 'mm', 'A4');
        $this->title        = $title;
        $this->headers      = $headers;
        $this->setTitle($this->title);
        $this->AddPage();
        //$this->header();
        $this->SetAutoPageBreak(false);
    }

    function AcceptPageBreak(){
        return false;
    }

    function add_header($header, $value){
        $this->SetFont('Helvetica', '', 12);
        $this->Cell(40, $this->line_h, iconv('UTF-8', 'windows-1252', $header).' ', 0, 0, "R", 0);
        $this->SetFont('Helvetica', 'B', 12);
        $this->Cell(160, $this->line_h, iconv('UTF-8', 'windows-1252', $value), 0, 1, "L", 0);
    }
    function header($pagenum=true){
        $this->SetTextColor(0, 0, 0);

        $this->SetXY(10,5);
        $this->SetFont('Helvetica', 'B', 14);
        $this->Cell(200, 10, $this->title, 0,  1, "C", 0);

        foreach($this->headers as $header=>$value){
            $this->add_header($header, $value);
        }

        // Table
        $this->line_y = $this->getY()+$this->line_h;
        $this->AddRow(
            _x('No', 'Bank slip table: number', 'bank-slip-for-woocommerce'),
            _x("Issuer's name", 'Bank slip table', 'bank-slip-for-woocommerce'),
            _x("Bank", 'Bank slip table', 'bank-slip-for-woocommerce'),
            _x('Amount', 'Bank slip table', 'bank-slip-for-woocommerce'),
            true
        );

        if($pagenum){
            $this->SetTextColor(80, 80, 80);
            $this->SetFont('Helvetica','B', 8);
            $this->SetXY(10, 285);
            $this->Cell(195, 1, $this->pageNo().'/{nb}', 0 ,0, 'R');
        }

    }

    /**
     * Add a row to the table
     * @param string  $no        [description]
     * @param string  $name      [description]
     * @param string  $amount    [description]
     * @param boolean $is_header [description]
     */
    function AddRow($no='', $name='', $reference='', $amount='', $is_header=false){
        if($this->line_y > 270){
            $this->AddPage();
            $this->header();
        }
        $line_h = $this->line_h;
        if(!empty($no)  || !empty($name) || !empty($amount)){
            $this->setXY(10, $this->line_y);
            $this->SetFont('Helvetica', $is_header ? 'B' : '', 10);
            $this->setXY(80, $this->line_y);
            $this->MultiCell(90, $line_h, iconv('UTF-8', 'windows-1252', $name).' ', empty($name) ? 0 : 1, $is_header ? "C" : "L", 0);
            $line_h = $this->getY()-$this->line_y;
            $this->setXY(10, $this->line_y);
            $this->Cell(20, $line_h, iconv('UTF-8', 'windows-1252', $no), empty($no) ? 0 : 1, 0, "C", 0);
            $this->Cell(50, $line_h, iconv('UTF-8', 'windows-1252', $reference).' ', empty($reference) ? 0 : 1, 0, $is_header ? "C" : "L", 0);
            $this->setXY(170, $this->line_y);
            $this->Cell(30, $line_h, ' '.iconv('UTF-8', 'windows-1252', $amount).' ', empty($amount) ? 0 : 1, 0, $is_header ? "C" : "R", 0);
        }
        $this->line_y+=$line_h;
    }

}
