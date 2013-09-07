<?php

/**
 * Mega.co.nz downloader
 * Require mcrypt, curl
 * @license GNU GPLv3 http://opensource.org/licenses/gpl-3.0.html
 * @author ZonD80
 */
class MEGA {

    private $seqno, $f;

    /**
     * Class constructor
     * @param string $file_hash File hash, coming after # in mega URL
     */
    function __construct($file_hash) {
        $this->seqno = 0;
        $this->f = $this->mega_get_file_info($file_hash);
    }

    function a32_to_str($hex) {
        return call_user_func_array('pack', array_merge(array('N*'), $hex));
    }

    function aes_ctr_decrypt($data, $key, $iv) {
        return mcrypt_decrypt(MCRYPT_RIJNDAEL_128, $key, $data, 'ctr', $iv);
    }

    function base64_to_a32($s) {
        return $this->str_to_a32($this->base64urldecode($s));
    }

    function base64urldecode($data) {
        $data .= substr('==', (2 - strlen($data) * 3) % 4);
        $data = str_replace(array('-', '_', ','), array('+', '/', ''), $data);
        return base64_decode($data);
    }

    function str_to_a32($b) {
        // Add padding, we need a string with a length multiple of 4
        $b = str_pad($b, 4 * ceil(strlen($b) / 4), "\0");
        return array_values(unpack('N*', $b));
    }

    /**
     * Handles query to mega servers
     * @param array $req data to be sent to mega
     * @return type
     */
    function mega_api_req($req) {

        $ch = curl_init('https://g.api.mega.co.nz/cs?id=' . ($this->seqno++)/* . ($sid ? '&sid=' . $sid : '') */);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(array($req)));
        $resp = curl_exec($ch);
        curl_close($ch);
        $resp = json_decode($resp, true);
        return $resp[0];
    }

    function aes_cbc_decrypt($data, $key) {
        return mcrypt_decrypt(MCRYPT_RIJNDAEL_128, $key, $data, MCRYPT_MODE_CBC, "\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0");
    }

    function mega_dec_attr($attr, $key) {
        $attr = trim($this->aes_cbc_decrypt($attr, $this->a32_to_str($key)));
        if (substr($attr, 0, 6) != 'MEGA{"') {
            return false;
        }
        return json_decode(substr($attr, 4), true);
    }

    /**
     * Downloads file from megaupload
     * @param string $as_attachment Download file as attachment, default true
     * @param string $local_path Save file to specified by $local_path folder
     * @return boolean True
     */
    function download($as_attachment = true, $local_path = null) {
        $ch = curl_init($this->f['binary_url']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        //curl_setopt($ch, CURLOPT_VERBOSE, true);
        $data_enc = curl_exec($ch);
        curl_close($ch);
        $data = $this->aes_ctr_decrypt($data_enc, $this->a32_to_str($this->f['k']), $this->a32_to_str($this->f['iv']));
        if ($as_attachment) {
            //die(var_dump($this->f['attr']['n']));
            header("Content-Disposition: attachment;filename=\"{$this->f['attr']['n']}\"");
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header("Content-Transfer-Encoding: binary");
            header("Content-Length: " . $this->f['size']);
            header('Pragma: no-cache');
            header('Expires: 0');
            print $data;
            return true;
        } else {
            file_put_contents($local_path . DIRECTORY_SEPARATOR . $this->f['attr']['n'], $data);
            return true;
        }
        /* $file_mac = cbc_mac($data, $k, $iv);
          print "\nchecking mac\n";
          if (array($file_mac[0] ^ $file_mac[1], $file_mac[2] ^ $file_mac[3]) != $meta_mac) {
          echo "MAC mismatch";
          } */
    }

    function get_chunks($size) {
        $chunks = array();
        $p = $pp = 0;

        for ($i = 1; $i <= 8 && $p < $size - $i * 0x20000; $i++) {
            $chunks[$p] = $i * 0x20000;
            $pp = $p;
            $p += $chunks[$p];
        }

        while ($p < $size) {
            $chunks[$p] = 0x100000;
            $pp = $p;
            $p += $chunks[$p];
        }

        $chunks[$pp] = ($size - $pp);
        if (!$chunks[$pp]) {
            unset($chunks[$pp]);
        }

        return $chunks;
    }

    /**
     * Downloads file from megaupload as a stream (useful if you want to implement megaupload proxy)
     * @param string $as_attachment Download file as attachment, default true
     * @param string $local_path Save file to specified by $local_path folder
     * @return boolean True
     */
    function stream_download($as_attachment = true, $local_path = null) {

        //$data = $this->aes_ctr_decrypt($data_enc, $this->a32_to_str($this->f['k']), $this->a32_to_str($this->f['iv']));
        if ($as_attachment) {
            header("Content-Disposition: attachment;filename=\"{$this->f['attr']['n']}\"");
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header("Content-Transfer-Encoding: binary");
            header("Content-Length: " . $this->f['size']);
            header('Pragma: no-cache');
            header('Expires: 0');
        } else {
            $destfile = fopen($local_path . DIRECTORY_SEPARATOR . $this->f['attr']['n'], 'wb');
        }
        $cipher = mcrypt_module_open(MCRYPT_RIJNDAEL_128, '', 'ctr', '');

        mcrypt_generic_init($cipher, $this->a32_to_str($this->f['k']), $this->a32_to_str($this->f['iv']));

        $chunks = $this->get_chunks($this->f['size']);

        $protocol = parse_url($this->f['binary_url'], PHP_URL_SCHEME);

        $opts = array(
            $protocol => array(
                'method' => 'GET'
            )
        );

        $context = stream_context_create($opts);
        $stream = fopen($this->f['binary_url'], 'rb', false, $context);

        $info = stream_get_meta_data($stream);
        $end = !$info['eof'];
        foreach ($chunks as $length) {

            $bytes = strlen($buffer);
            while ($bytes < $length && $end) {
                $data = fread($stream, min(1024, $length - $bytes));
                $buffer .= $data;

                $bytes = strlen($buffer);
                $info = stream_get_meta_data($stream);
                $end = !$info['eof'] && $data;
            }

            $chunk = substr($buffer, 0, $length);
            $buffer = $bytes > $length ? substr($buffer, $length) : '';

            $chunk = mdecrypt_generic($cipher, $chunk);
            if ($as_attachment)
                print $chunk;
            else
                fwrite($destfile, $chunk);
        }

        // Terminate decryption handle and close module
        mcrypt_generic_deinit($cipher);
        mcrypt_module_close($cipher);
        fclose($stream);
        if (!$as_attachment)
            fclose($destfile);

        return true;
        /* $file_mac = cbc_mac($data, $k, $iv);
          print "\nchecking mac\n";
          if (array($file_mac[0] ^ $file_mac[1], $file_mac[2] ^ $file_mac[3]) != $meta_mac) {
          echo "MAC mismatch";
          } */
    }

    private function mega_get_file_info($hash) {
        preg_match('/\!(.*?)\!(.*)/', $hash, $matches);
        $id = $matches[1];
        $key = $matches[2];
        $key = $this->base64_to_a32($key);
        $k = array($key[0] ^ $key[4], $key[1] ^ $key[5], $key[2] ^ $key[6], $key[3] ^ $key[7]);
        $iv = array_merge(array_slice($key, 4, 2), array(0, 0));
        $meta_mac = array_slice($key, 6, 2);
        $info = $this->mega_api_req(array('a' => 'g', 'g' => 1, 'p' => $id));
        if (!$info['g']) die('No such file on mega. Maybe it was deleted.');
        return array('id' => $id, 'key' => $key, 'k' => $k, 'iv' => $iv, 'meta_mac' => $meta_mac, 'binary_url' => $info['g'], 'attr' => $this->mega_dec_attr($this->base64urldecode($info['at']), $k), 'size' => $info['s']);
    }

    /**
     * Returns file information
     * @return array File information
     */
    public function file_info() {
        return $this->f;
    }

}

?>