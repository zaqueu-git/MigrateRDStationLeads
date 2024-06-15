<?php
class Migration {
    private $conn;

    public function __construct()
    {
        $this->getDatabase();
        $this->search();
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
    
    private function search()
    {
        $leads = $this->getLeadsPrepareds();

        if (empty($leads)) {
            echo "FIM";
            die();
        }

        array_walk($leads, [$this, 'prepared']);

        echo "CONTINUA";
        die();
    }

    private function getLeadsPrepareds()
    {
        $stmt = $this->conn->prepare("SELECT * FROM mkt_list WHERE status = 'Preparado' LIMIT 100");

        if ($stmt->execute()) {
            return $stmt->fetchAll(PDO::FETCH_OBJ);
        }
    
        return [];
    }

    private function prepared($obj)
    {
        $id = $obj->id;
        $json = json_decode($obj->json);

        //echo "ID= " . $id . "<br><br>";

        /*-- TEMPLATE --*/

        $data = [
            "date" => "",
            "name" => "",
            "email" => "",
            "phone" => "",
            "seller" => "",
            "unity" => "",
            "origin" => "RD Station",
            "stage" => "",
            "temperature" => "",
            "period" => "",
            "notes" => "",
            "course" => ""
        ];

        ////////////////////////////////////////////////////////////////////////        

        /*-- DATA --*/

        if (isset($json->created_at)) {
            $data["date"] = $json->created_at;
        }

        /*-- NOME DO CLIENTE --*/

        if (isset($json->name)) {
            $data["name"] = $json->name;
        }

        /*-- E-MAIL DO CLIENTE --*/

        if (isset($json->email)) {
            $data["email"] = $json->email;
        }

        ////////////////////////////////////////////////////////////////////////

        /*-- BUSCA O CONTATO NO RD STATION --*/

        $contact = $this->searchContactRD($data["email"]);


        /*-- TELEFONE DO CLIENTE --*/

        if (isset($contact->personal_phone)) {
            $data["phone"] = $contact->personal_phone;
        }

        /*-- OBSERVAÇÕES DO CLIENTE --*/
        
        $search = 'cf_';
       
        foreach ($contact as $key => $value) {
            if(preg_match("/{$search}/i", $key)) {
                $data["notes"] .= $key .": ". $value . "\n";
            }
        }

        /*-- CAMPOS PERSONALIZADOS --*/
        
        $customFields = [];
        $search = 'cf_';
       
        foreach ($contact as $key => $value) {
            if(preg_match("/{$search}/i", $key)) {
                $customFields[$key] = $value;
            }
        }

        /*-- UNIDADE DO CLIENTE --*/

        $search = 'unidade';
       
        foreach ($customFields as $key => $value) {
            if(preg_match("/{$search}/i", $key)) {
                $data["unity"] = $value;
                break;
            }
        }

        /*-- CURSO DO CLIENTE --*/

        $search = 'curso';
       
        foreach ($customFields as $key => $value) {
            if(preg_match("/{$search}/i", $key)) {
                $data["course"] = $value;
                break;
            }
        }
        
        /*-- SALVAR --*/

        $result = $this->save($data);

        if (isset($result->status)) {
            if ($result->status == "success") {
                $this->alterStatus($id, "OK");
                return 0;
            }

            if ($result->status == "reapeat-success") {
                $this->alterStatus($id, "OK2");
                return 0;
            }

            $this->alterStatus($id, "E1");
            return 0;
        }
        
        $this->alterStatus($id, "E2");
        return 0;
    }

    private function save($data)
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            
            CURLOPT_URL => 'http:///api/crm/v1/lead/marketing/cadastrar',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($data, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
            ),
        ));
    
        $response = curl_exec($curl);
        $error = curl_error($curl);

        curl_close($curl);

        if ($error) {
            return "Erro curl";
        }  

        return json_decode($response);
    }

    private function searchContactRD($email)
    {
        $token = $this->getTokenBD();

        if (empty($token)) {
            return false;
        }

        $curl = curl_init();

        curl_setopt_array($curl, array(
          CURLOPT_URL => 'https://api.rd.services/platform/contacts/email:' . $email,
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

        if (!isset($response->uuid)) {
            return [];
        }

        return $response;
    }

    private function alterStatus($id, $status)
    {
        $stmt = $this->conn->prepare("UPDATE mkt_list SET status = :status WHERE id = :id");
        $stmt->bindValue(":id", $id, PDO::PARAM_INT);
        $stmt->bindValue(":status", $status, PDO::PARAM_STR);

        if ($stmt->execute()) {
            return true;
        }
    
        return false;
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
            "client_secret": "id",
            "refresh_token": "id"
        }',
          CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json',
            'Cookie: __rdsid=a94255aa095097a56aa7aa35bd916294'
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
    fetch("http:///localhost/marketing_ler.php?init=S")
    .then(response => response.text())
    .then(data => {
        if (data == "CONTINUA" || data == "TOKEN") {
            send();
        }
    });
}

send();
</script>