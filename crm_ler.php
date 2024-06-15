<?php
class Migration {
    private $conn;

    public function __construct()
    {
        $this->getDatabase();
        $this->search();
    }

    private function shutdownPc()
    {
        // Comando de desligamento específico para Windows
        $command = 'shutdown.exe /s /t 0';

        // Executa o comando
        exec($command, $output, $returnCode);

        // Verifica se o comando foi executado com sucesso
        if ($returnCode === 0) {
            echo "O computador está sendo desligado...";
        } else {
            echo "Ocorreu um erro ao tentar desligar o computador.";
        }        
    }

    private function getLast5000Characters($texto) {
        $limite = 1000;
        $tamanhoTexto = strlen($texto);
    
        if ($tamanhoTexto > $limite) {
            $posicaoInicial = $tamanhoTexto - $limite;
            $texto = substr($texto, $posicaoInicial);
        }
    
        return $texto;
    }   

    private function save($data)
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'http://localhost/api/crm/v1/lead/cadastrar',
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

        //var_dump($response);
        return json_decode($response);
    }

    private function prepared($obj)
    {
        $id = $obj->id;
        $json = json_decode($obj->json);
        $notes = $obj->notes;

        //echo "ID= " . $id . "<br><br>";

        /*-- TEMPLATE --*/

        $data = [
            "date" => "",
            "name" => "",
            "email" => "",
            "phone" => "",
            "seller" => "",
            "unity" => "3",
            "origin" => "RD Station",
            "stage" => "",
            "temperature" => "",
            "period" => "",
            "notes" => "",
            "course" => "",
            "central" => "n",
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

        /*-- VENDEDOR --*/

        /*
        if (isset($json->organization->user->email)) {
            $data["seller"] = $json->organization->user->email;
        } else if (isset($json->user->email)) {
            $data["seller"] = $json->user->email;            
        }
        */

        if (isset($json->user->email)) {
            $data["seller"] = $json->user->email;            
        }
    
        /*-- FASE DO CLIENTE --*/

        if (isset($json->deal_stage->name)) {
            $data["stage"] = $json->deal_stage->name;
        }

        /*-- E-MAIL DO CLIENTE --*/

        if (isset($json->contacts[0]->emails[0]->email)) {
            $data["email"] = $json->contacts[0]->emails[0]->email;
        }

        /*-- TELEFONE DO CLIENTE --*/

        if (isset($json->contacts[0]->phones[0]->phone)) {
            $data["phone"] = $json->contacts[0]->phones[0]->phone;
        }

        /*-- CURSO DO CLIENTE --*/

        if (isset($json->deal_products[0]->name)) {
            $data["course"] = $json->deal_products[0]->name;
        }

        if (empty($data["email"]) && empty($data["phone"])) {
            $data["phone"] = "(XX) XXXXX-XXXX";
        }

        /*-- ANOTAÇÕES --*/   
        
        $data["notes"] = str_replace("<br/>", "\n", $notes) . "\n";

        ////////////////////////////////////////////////////////////////////////

        /*-- BUSCA O CONTATO NO RD STATION --*/

        $contact = $this->searchContactRD($data["email"]);

        /*-- OBSERVAÇÕES DO CLIENTE --*/
        
        $search = 'cf_';
       
        foreach ($contact as $key => $value) {
            if(preg_match("/{$search}/i", $key)) {
                $data["notes"] .= $key .": ". $value . "\n";
            }
        }

        if (empty($data["notes"])) {
            $data["notes"] = "";
        } else {
            $data["notes"] = $this->getLast5000Characters($data["notes"]);
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

        /*
        $search = 'unidade';
       
        foreach ($customFields as $key => $value) {
            if(preg_match("/{$search}/i", $key)) {
                $data["unity"] = $value;
                break;
            }
        }
        */

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

            $this->alterStatus($id, "E1");
            return 0;
        }
        
        $this->alterStatus($id, "E2");
        return 0;
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

    private function search()
    {
        $leads = $this->getLeadsPrepareds();

        if (empty($leads)) {
            echo "FIM";
            //$this->shutdownPc();
            die();
        }

        array_walk($leads, [$this, 'prepared']);

        echo "CONTINUA";
        die();
    }

    private function alterStatus($id, $status)
    {
        $stmt = $this->conn->prepare("UPDATE crm_list SET status = :status WHERE id = :id");
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

    private function getLeadsPrepareds()
    {
        $stmt = $this->conn->prepare("SELECT * FROM crm_list WHERE status = 'Preparado' LIMIT 50");

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

if (isset($_GET["init"])) {
    if ($_GET["init"] == "S") {
        new Migration();
        die();
    }
}
?> 

<script>
function send() {
    fetch("http:///localhost/crm_ler.php?init=S")
    .then(response => response.text())
    .then(data => {
        if (data != "FIM") {
            send();
        }
    })
    .catch(error => {
        console.error("Ocorreu um erro na solicitação:", error);
        // Chamar send() novamente em caso de falha
        send();
    });
}

send();
</script>
