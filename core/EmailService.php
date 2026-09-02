<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';
require_once __DIR__.'/emailTemplates.php';
class EmailService {
    public function configByType(int $type):?array {
        $st=db()->prepare('SELECT * FROM correo WHERE correo_tipo_id=? AND estado=1 ORDER BY correo_id DESC LIMIT 1');
        $st->execute([$type]);
        return $st->fetch()?:null;
    }
    public function configById(int $id):?array {
        $st=db()->prepare('SELECT * FROM correo WHERE correo_id=? LIMIT 1');
        $st->execute([$id]);
        return $st->fetch()?:null;
    }
    public function sendByType(int $type,string $to,string $subject,string $html):array {
        $cfg=$this->configByType($type);
        if(!$cfg)return ['success'=>false,
        'message'=>'No active email configuration for this type.'];
        return $this->send($cfg,$to,$subject,$html);
    }
    public function sendWithFallback(array $types,string $to,string $subject,string $html):array {
        foreach($types as $type) {
            $cfg=$this->configByType((int)$type);
            if($cfg) {
                $result=$this->send($cfg,$to,$subject,$html);
                if($result['success'])return $result;
                $last=$result;
            }
        }
        return $last??['success'=>false,
        'message'=>'No active email configuration is available.'];
    }
    public function test(int $id,string $to=''):array {
        $cfg=$this->configById($id);
        if(!$cfg)return ['success'=>false,
        'message'=>'Email configuration not found.'];
        $dest=$to!==''?$to:$cfg['correo'];
        return $this->send($cfg,$dest,'Castro\'s Ready email test',EmailTemplates::test($cfg['metodo_envio'],settings()));
    }
    public function send(array $cfg,string $to,string $subject,string $html):array {
        if(!filter_var($to,FILTER_VALIDATE_EMAIL))return ['success'=>false,
        'message'=>'Invalid destination email.'];
        return strtoupper($cfg['metodo_envio'])==='GRAPH'?$this->graph($cfg,$to,$subject,$html):$this->smtp($cfg,$to,$subject,$html);
    }
    private function graph(array $c,string $to,string $subject,string $html):array {
        if(!function_exists('curl_init'))return ['success'=>false,
        'message'=>'cURL is not enabled on this server.'];
        $tenant=trim((string)$c['tenant_id']);
        $client=trim((string)$c['client_id']);
        $secret=secret_decrypt($c['client_secret']??'');
        $from=trim((string)($c['graph_user']?:$c['correo']));
        if(!$tenant||!$client||!$secret||!filter_var($from,FILTER_VALIDATE_EMAIL))return ['success'=>false,
        'message'=>'Graph credentials are incomplete.'];
        $ch=curl_init('https://login.microsoftonline.com/'.rawurlencode($tenant).'/oauth2/v2.0/token');
        curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>30,CURLOPT_POSTFIELDS=>http_build_query(['client_id'=>$client,'scope'=>'https://graph.microsoft.com/.default','client_secret'=>$secret,'grant_type'=>'client_credentials']),CURLOPT_HTTPHEADER=>['Content-Type: application/x-www-form-urlencoded']]);
        $raw=curl_exec($ch);
        $code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);
        $err=curl_error($ch);
        curl_close($ch);
        $json=json_decode((string)$raw,true);
        if($code<200||$code>=300||empty($json['access_token']))return ['success'=>false,
        'message'=>'Graph token error: '.($err?:('HTTP '.$code))];
        $payload=['message'=>['subject'=>$subject,'body'=>['contentType'=>'HTML','content'=>$html],'toRecipients'=>[['emailAddress'=>['address'=>$to]]]],
        'saveToSentItems'=>(int)($c['save_to_sent_items']??1)===1];
        $ch=curl_init('https://graph.microsoft.com/v1.0/users/'.rawurlencode($from).'/sendMail');
        curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>45,CURLOPT_POSTFIELDS=>json_encode($payload,JSON_UNESCAPED_UNICODE),CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$json['access_token'],'Content-Type: application/json']]);
        $resp=curl_exec($ch);
        $code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);
        $err=curl_error($ch);
        curl_close($ch);
        return $code===202?['success'=>true,
        'message'=>'Email sent with Microsoft Graph.']:['success'=>false,
        'message'=>'Graph send error: '.($err?:('HTTP '.$code.' '.$resp))];
    }
    private function smtp(array $c,string $to,string $subject,string $html):array {
        $host=trim((string)$c['server']);
        $port=(int)($c['port']?:587);
        $secure=strtolower(trim((string)$c['smtp_secure']));
        $user=trim((string)$c['correo']);
        $pass=secret_decrypt($c['password']??'');
        if(!$host||!filter_var($user,FILTER_VALIDATE_EMAIL)||$pass==='')return ['success'=>false,
        'message'=>'SMTP configuration is incomplete.'];
        $target=($secure==='ssl'?'ssl://':'tcp://').$host.':'.$port;
        $fp=@stream_socket_client($target,$errno,$errstr,15,STREAM_CLIENT_CONNECT);
        if(!$fp)return ['success'=>false,
        'message'=>'SMTP connection failed: '.$errstr];
        stream_set_timeout($fp,20);
        try {
            $this->expect($fp,[220]);
            $this->cmd($fp,'EHLO '.($_SERVER['HTTP_HOST']??'localhost'),[250]);
            if($secure==='tls') {
                $this->cmd($fp,'STARTTLS',[220]);
                if(!stream_socket_enable_crypto($fp,true,STREAM_CRYPTO_METHOD_TLS_CLIENT))throw new RuntimeException('Unable to enable TLS.');
                $this->cmd($fp,'EHLO '.($_SERVER['HTTP_HOST']??'localhost'),[250]);
            }
            $this->cmd($fp,'AUTH LOGIN',[334]);
            $this->cmd($fp,base64_encode($user),[334]);
            $this->cmd($fp,base64_encode($pass),[235]);
            $this->cmd($fp,'MAIL FROM:<'.$user.'>',[250]);
            $this->cmd($fp,'RCPT TO:<'.$to.'>',[250,251]);
            $this->cmd($fp,'DATA',[354]);
            $boundary='b'.bin2hex(random_bytes(8));
            $headers=['From: Castro\'s Ready <'.$user.'>',
            'To: <'.$to.'>',
            'Subject: =?UTF-8?B?'.base64_encode($subject).'?=',
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'Content-Transfer-Encoding: base64'];
            $body=implode("\r\n",$headers)."\r\n\r\n".chunk_split(base64_encode($html))."\r\n.";
            fwrite($fp,$body."\r\n");
            $this->expect($fp,[250]);
            $this->cmd($fp,'QUIT',[221]);
            fclose($fp);
            return ['success'=>true,
            'message'=>'Email sent with SMTP.'];
        } catch(Throwable $e) {
            @fwrite($fp,"QUIT\r\n");
            @fclose($fp);
            return ['success'=>false,
            'message'=>$e->getMessage()];
        }
    }
    private function cmd($fp,string $cmd,array $codes):void {
        fwrite($fp,$cmd."\r\n");
        $this->expect($fp,$codes);
    }
    private function expect($fp,array $codes):void {
        $resp='';
        while(($line=fgets($fp,515))!==false) {
            $resp.=$line;
            if(strlen($line)<4||$line[3]!=='-')break;
        }
        $code=(int)substr($resp,0,3);
        if(!in_array($code,$codes,true))throw new RuntimeException('SMTP error '.$code.': '.trim($resp));
    }
}
