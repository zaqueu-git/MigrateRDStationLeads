<?php
class Migration {
    private $conn;

    public function __construct()
    {
        $this->getDatabase();
        $this->search();
    }

    private function alterContactRD($email)
    {
        $token = $this->getTokenBD();

        $curl = curl_init();

        curl_setopt_array($curl, array(
          CURLOPT_URL => 'https://api.rd.services/platform/contacts/email:'. $email .'/tag',
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => '',
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 0,
          CURLOPT_FOLLOWLOCATION => true,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => 'POST',
          CURLOPT_POSTFIELDS =>'{
            "tags": [
                "nome-da-tag"
            ]
        }',
          CURLOPT_HTTPHEADER => array(
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
          ),
        ));

        $response = curl_exec($curl);
        $error = curl_error($curl);

        curl_close($curl);

        if ($error) {
            return false;
        }
    }

    private function save($obj, $arg)
    {
        $json = json_encode($obj, JSON_UNESCAPED_UNICODE);
        $status = "Preparado";

        $stmt = $this->conn->prepare("INSERT INTO mkt_list (json, status) VALUES (:json, :status) ");
        
        $stmt->bindValue(":json", $json, PDO::PARAM_STR);
        $stmt->bindValue(":status", $status, PDO::PARAM_STR);
    
        if ($stmt->execute()) {
            if ($stmt->rowCount() > 0) {
                $this->alterContactRD($obj->email);
                return $this->conn->lastInsertId();
            }
        }
        
        return false;
    }

    private function search()
    {
        $token = $this->getTokenBD();

        if (empty($token)) {
            return false;
        }

        $curl = curl_init();

        curl_setopt_array($curl, array(
          CURLOPT_URL => 'https://api.rd.services/platform/segmentations/7538267/contacts',
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => '',
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 0,
          CURLOPT_FOLLOWLOCATION => true,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => 'GET',
          CURLOPT_HTTPHEADER => array(
            'Authorization: Bearer ' . $token,
          ),
        ));
    
        $response = curl_exec($curl);
        $error = curl_error($curl);

        curl_close($curl);

        if ($error) {
            return false;
        }   

        $response = json_decode($response);

        if (isset($response->error)) {
            if ($response->error == "invalid_token") {
                $this->getTokenRD();
                echo "TOKEN";
                die();                
            }
        }

        if (!isset($response->contacts)) {
            echo "FIM";
            die();
        }

        $contacts = $response->contacts;

        if (empty($contacts)) {
            echo "FIM";
            die();
        }

        array_walk($contacts, [$this, 'save']);

        echo "CONTINUA";
        die();
    }

    private function alterTokenBD($value)
    {
        $stmt = $this->conn->prepare("UPDATE mkt_config SET script_token = :script_token WHERE id = 1");
        $stmt->bindValue(":script_token", $value, PDO::PARAM_STR);

        if ($stmt->execute()) {
            return true;
        }
    
        return false;
    }

    private function alterDateToken($value)
    {
        $stmt = $this->conn->prepare("UPDATE mkt_config SET script_date_token = :script_date_token WHERE id = 1");
        $stmt->bindValue(":script_date_token", $value, PDO::PARAM_STR);

        if ($stmt->execute()) {
            return true;
        }
    
        return false;
    }

    private function getTokenRD()
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
          CURLOPT_URL => 'https://api.rd.services/auth/token',
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => '',
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 0,
          CURLOPT_FOLLOWLOCATION => true,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => 'POST',
          CURLOPT_POSTFIELDS =>'{
            "client_id": "id",
            "client_secret": "secret",
            "refresh_token": "token"
        }',
          CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json',
          ),
        ));
    
        $response = curl_exec($curl);
        $error = curl_error($curl);

        curl_close($curl);

        if ($error) {
            return false;
        }   

        $response = json_decode($response);

        $token = "";

        if (isset($response->access_token)) {
            $token = $response->access_token;

            $this->alterDateToken(date("Y-m-d"));
        }
        
        $this->alterTokenBD($token);
    }

    private function getTokenBD()
    {
        $stmt = $this->conn->prepare("SELECT script_token FROM mkt_config WHERE id = 1");

        if ($stmt->execute()) {
            return $stmt->fetch(PDO::FETCH_OBJ)->script_token;
        }
    
        return "";
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

if (isset($_GET["init"])) {
    if ($_GET["init"] == "S") {
        new Migration();
        die();
    }
}
?> 

<script>
function send() {
    fetch("http:///script-para-migrar-leads-rd-station/mkt.php?init=S")
    .then(response => response.text())
    .then(data => {
        if (data == "CONTINUA" || data == "TOKEN") {
            send();
        }
    });
}

send();
</script>