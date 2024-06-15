<?php
class Migration {
    private $conn;

    public function __construct()
    {
        $this->getDatabase();
        $this->search();
    }

    private function formatPhone($phone)
    {
        $formatedPhone = preg_replace('/[^0-9]/', '', $phone);
        
        if (substr($formatedPhone, 0, 2) == "55") {
            $formatedPhone = substr($formatedPhone, 2, strlen($formatedPhone));
        }

        $matches = [];

        preg_match('/^([0-9]{2})([0-9]{4,5})([0-9]{4})$/', $formatedPhone, $matches);

        if ($matches) {
            return '('.$matches[1].') '.$matches[2].'-'.$matches[3];
        }
    
        return $formatedPhone;
    }

    private function alterPhone($obj)
    {
        echo $obj->id . "<br>";

        $phone = $this->formatPhone($obj->phone);

        $stmt = $this->conn->prepare("UPDATE clients SET phone = :phone WHERE id = $obj->id");
        $stmt->bindValue(":phone", $phone, PDO::PARAM_STR);

        if ($stmt->execute()) {
            return true;
        }
    
        return false;
    }

    private function search()
    {
        $clients = $this->getClients();

        if (empty($clients)) {
            echo "FIM";
            die();
        }

        array_walk($clients, [$this, 'alterPhone']);

        ECHO "FIM";
    }

    private function getClients()
    {
        $stmt = $this->conn->prepare("SELECT * FROM clients WHERE id > 110478 AND id < 110479 ORDER BY id ASC LIMIT 15000");

        if ($stmt->execute()) {
            return $stmt->fetchAll(PDO::FETCH_OBJ);
        }
    
        return [];
    }

    private function getDatabase()
    {
        $dbServer = "";
        $dbName = "";
        $dbUser = "";
        $dbPassword = "";
        $dbDriver = 'mysql:dbname='. $dbName .';host=' . $dbServer;
    
        $conn = new PDO($dbDriver,$dbUser,$dbPassword);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $conn->exec("set names utf8");
    
        $this->conn = $conn;
    }
}

new Migration();
?> 
