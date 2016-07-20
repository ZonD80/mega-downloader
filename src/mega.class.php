<?php
	
	ini_set("allow_url_fopen", "On");
	
	require "vendor/autoload.php";

	use GuzzleHttp\Client;
	use Psr\Http\Message\ResponseInterface;
	use GuzzleHttp\Exception\RequestException;
	use GuzzleHttp\Psr7;

	/**
	* mega.co.nz downloader
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
		public function __construct($file_hash, $folder_id='') {
			$this -> seqno = 1;
			if(preg_match('/\#F/', $file_hash)) {
				$this -> files = $this -> mega_get_folder_info($file_hash);
				$this -> is_folder = true;
			}
			else {
				$this -> f = $this -> mega_get_file_info($file_hash, $folder_id);
				$this -> is_folder = false;
			}
		}

		public function a32_to_str($hex) {
			return call_user_func_array('pack', array_merge(array('N*'), $hex));
		}

		public function aes_ctr_decrypt($data, $key, $iv) {
			return mcrypt_decrypt(MCRYPT_RIJNDAEL_128, $key, $data, 'ctr', $iv);
		}

		public function base64_to_a32($s) {
			return $this -> str_to_a32($this -> base64urldecode($s));
		}

		public function aes_cbc_decrypt_a32($data, $key) {
			return $this -> str_to_a32($this -> aes_cbc_decrypt($this -> a32_to_str($data), $this -> a32_to_str($key)));
		}

		public function decrypt_key($a, $key) {
			$x = array();

			for ($i = 0; $i < count($a); $i += 4) {
				$x = array_merge($x, $this -> aes_cbc_decrypt_a32(array_slice($a, $i, 4), $key));
			}

			return $x;
		}

		public function base64urldecode($data) {
			$data .= substr('==', (2 - strlen($data) * 3) % 4);
			$data = str_replace(array('-', '_', ','), array('+', '/', ''), $data);
			return base64_decode($data);
		}

		public function str_to_a32($b) {
			// Add padding, we need a string with a length multiple of 4
			$b = str_pad($b, 4 * ceil(strlen($b) / 4), "\0");
			return array_values(unpack('N*', $b));
		}

		/**
		* Handles query to mega servers
		* @param array $req data to be sent to mega
		* @return string
		*/
	
		public function mega_api_req($req,$get=array()) {
			$this -> seqno = $this -> seqno + 1;
			$get['id'] = $this -> seqno;
			
			$client = new Client(["verify" => false]);
			// Provide the body as a string or json.
			$response = $client -> request('POST', 'https://g.api.mega.co.nz/cs?'.http_build_query($get), [
				'body' => json_encode(array($req))
			]);
		
			$resp = json_decode($response -> getBody() -> getContents(), true);
			return $resp[0];
		}

		public function aes_cbc_decrypt($data, $key) {
			return mcrypt_decrypt(MCRYPT_RIJNDAEL_128, $key, $data, MCRYPT_MODE_CBC, "\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0");
		}

		public function mega_dec_attr($attr, $key) {
			$attr = trim($this -> aes_cbc_decrypt($attr, $this -> a32_to_str($key)));
			if(substr($attr, 0, 6) != 'MEGA{"') {
				return false;
			}
			return json_decode(substr($attr, 4), true);
		}

		public function get_chunks($size) {
			$chunks = array();
			$p = $pp = 0;

			for($i = 1; $i <= 8 && $p < $size - $i * 0x20000; $i++) {
				$chunks[$p] = $i * 0x20000;
				$pp = $p;
				$p += $chunks[$p];
			}

			while($p < $size) {
				$chunks[$p] = 0x100000;
				$pp = $p;
				$p += $chunks[$p];
			}

			$chunks[$pp] = ($size - $pp);
			if(!$chunks[$pp]) {
				unset($chunks[$pp]);
			}

			return $chunks;
		}


		public function download_zip() {
			$temp_file_path = "";
			
			if($this -> is_folder) {
				$zip_file_name = 'folder.zip';
				
				if(file_exists('./' . $zip_file_name))
					@unlink('./' . $zip_file_name);
				
				$zip = \Comodojo\Zip\Zip::create('./' . $zip_file_name);
				
				foreach ($this -> files as $url) {
					$mega = new MEGA($url, $this -> folder['id']);
					$file_info = $mega -> file_info();
					
					$temp_file_path = "./";
					
					$file_name = $file_info['attr']['n'];
					$mega -> download(false, $temp_file_path . base64_encode($file_name));
					
					$this -> recover_file_name($temp_file_path, base64_encode($file_name));
					
					$zip -> add($temp_file_path . $file_name);
				}
				
				$zip -> close();
				
				//delete temp files
				foreach ($this -> files as $url) {
					$mega = new MEGA($url, $this -> folder['id']);
					$file_info = $mega -> file_info();
					$temp_file_path = "./";
					
					$file_name = $file_info['attr']['n'];
					@unlink($temp_file_path . base64_encode($file_name));
				}
			}
			else {
				$zip = \Comodojo\Zip\Zip::create("{$this -> f['attr']['n']}.zip");
				
				$temp_file_path = "./";
				
				$file_name = $this -> f['attr']['n'];
				$this -> download(false, $temp_file_path . base64_encode($file_name));
				
				$this -> recover_file_name($temp_file_path, base64_encode($file_name));
				
				$zip -> add($temp_file_path . $file_name);
				$zip -> close();
				
				@unlink($temp_file_path . base64_encode($file_name));
				
			}
			
		}
		
		public function download_file() {
			$temp_file_path = "";
			
			if($this -> is_folder) {
				
				foreach ($this -> files as $url) {
					$mega = new MEGA($url, $this -> folder['id']);
					$file_info = $mega -> file_info();
					
					$temp_file_path = "./";
					
					$file_name = $file_info['attr']['n'];
					$mega -> download(false, $temp_file_path . base64_encode($file_name));
				}
			}
			else {
				
				$temp_file_path = "./";
				
				$file_name = $this -> f['attr']['n'];
				$this -> download(false, $temp_file_path . base64_encode($file_name));
				
			}
			
			$this -> recover_file_name($temp_file_path, base64_encode($file_name));
			
		}

		/**
		* Downloads file from MEGA
		* @param string $as_attachment Download file as attachment, default true
		* @param string $local_path Save file to specified by $local_path folder
		* @return boolean True
		*/
		public function download($as_attachment = false, $local_path = null) {
			/*
			if($this -> is_folder) {
				die("You can not download raw folders. Use download_zip() instead.\n");
			}
			*/

			echo "download starts...\n";
		
			if($as_attachment) {
				ob_start();
				header("Content-Disposition: attachment;filename=\"{$this -> f['attr']['n']}\"");
				header('Content-Description: File Transfer');
				header('Content-Type: application/octet-stream; charset=utf-8');
				header("Content-Transfer-Encoding: binary");
				header("Content-Length: " . $this -> f['size']);
				header('Pragma: no-cache');
				header('Expires: 0');
			}
			else {
				if($local_path == null)
					$local_path = "./";
				
				$destfile = fopen($local_path, 'w+');
			}
			
			$cipher = mcrypt_module_open(MCRYPT_RIJNDAEL_128, '', 'ctr', '');

			mcrypt_generic_init($cipher, $this -> a32_to_str($this -> f['k']), $this -> a32_to_str($this -> f['iv']));

			$chunks = $this -> get_chunks($this -> f['size']);

			$protocol = parse_url($this -> f['binary_url'], PHP_URL_SCHEME);

			$opts = array(
				$protocol => array(
					'method' => 'GET'
				)
			);
			
			$context = stream_context_create($opts, ['notification' => [$this, 'progress']]);
			$resource = fopen($this -> f['binary_url'], 'rb', false, $context);
			
			$stream = Psr7\stream_for($resource);
		
			$info = $stream -> getMetadata();
			
			$end = !$info['eof'];
			$buffer = "";
		
			foreach($chunks as $length) {
			
				$bytes = 0;
				while($bytes < $length && $end) {
					$data = $stream -> read(min(1024, $length - $bytes));
					$buffer .= $data;

					$bytes = strlen($buffer);
					$info = $stream -> getMetadata();
					$end = !$info['eof'] && $data;
				}
			
				$chunk = substr($buffer, 0, $length);
				$buffer = $bytes > $length ? substr($buffer, $length) : '';

				$chunk = mdecrypt_generic($cipher, $chunk);
				
				if($as_attachment) {
					echo $chunk . "\n";
					ob_flush();
				}
				else {
					fwrite($destfile, $chunk);
				}
			}

			// Terminate decryption handle and close module
			mcrypt_generic_deinit($cipher);
			mcrypt_module_close($cipher);
			
			if(!$as_attachment) {
				fclose($destfile);
			}

			return true;
			
			/* $file_mac = cbc_mac($data, $k, $iv);
			print "\nchecking mac\n";
			if (array($file_mac[0] ^ $file_mac[1], $file_mac[2] ^ $file_mac[3]) != $meta_mac) {
			echo "MAC mismatch";
			} */
		}

		private function mega_get_file_info($hash, $folder_id='') {
			preg_match('/\!(.*?)\!(.*)/', $hash, $matches);
			$id = $matches[1];
			$key = $matches[2];
			$key = $this -> base64_to_a32($key);
			$key_len = count($key);
			
			if($key_len == 4)
				$k = array($key[0], $key[1], $key[2], $key[3]);
			else if($key_len == 8)
				$k = array($key[0] ^ $key[4], $key[1] ^ $key[5], $key[2] ^ $key[6], $key[3] ^ $key[7]);
			else
				die("Invalid key, please verify your MEGA url.");
			
			$iv = array_merge(array_slice($key, 4, 2), array(0, 0));
			$meta_mac = array_slice($key, 6, 2);

			if(!$folder_id) {
				$info = $this -> mega_api_req(array('a' => 'g', 'g' => 1, 'p' => $id));
            }
			else {
				$info = $this -> mega_api_req(array('a' => 'g', 'g' => 1, 'n' => $id),array('n' => $folder_id));
			}
		
			if(!$info['g']) {
				die('No such file on mega. Maybe it was deleted.\n');
			}
		
			return array('id' => $id, 'key' => $key, 'k' => $k, 'iv' => $iv, 'meta_mac' => $meta_mac, 'binary_url' => $info['g'], 
				'attr' => $this -> mega_dec_attr($this -> base64urldecode($info['at']), $k), 'size' => $info['s']);
		}

		private function mega_get_folder_info($hash) {
			preg_match('/\!(.*?)\!(.*)/', $hash, $matches);
			$id = $matches[1];
			$key = $matches[2];
			$key = $this -> base64_to_a32($key);
			$key_len = count($key);
			
			if($key_len == 4)
				$k = array($key[0], $key[1], $key[2], $key[3]);
			else if($key_len == 8)
				$k = array($key[0] ^ $key[4], $key[1] ^ $key[5], $key[2] ^ $key[6], $key[3] ^ $key[7]);
			else
				die("Invalid key, please verify your MEGA url.");
			
			$iv = array_merge(array_slice($key, 4, 2), array(0, 0));
			$meta_mac = array_slice($key, 6, 2);

			$folder_info = $this -> mega_api_req(array('a' => 'f','c' => 1,'ca' => 1,'r' => 1),array('n' => $id));

			if(!$folder_info['f']) {
				die('No such folder on mega. Maybe it was deleted.\n');
			}
			
			if(!isset($folder_info['at'])) {
				$folder_info['at'] = null;
			}

			$this -> folder = array('id' => $id, 'key' => $key, 'k' => $k, 'iv' => $iv, 'meta_mac' => $meta_mac,
				'attr' => $this -> mega_dec_attr($this -> base64urldecode($folder_info['at']), $k));

			foreach ($folder_info['f'] as $file) {
				if($file['t'] != 0) {
					continue;
				}
				
				$file_key = substr($file['k'], strpos($file['k'], ':') + 1);
				$file_key = $this -> decrypt_key($this -> base64_to_a32($file_key), $key);
				$file_key = base64_encode($this -> a32_to_str($file_key));
				$return[] = "!{$file['h']}!{$file_key}";
			}

			return $return;
		}

		/**
		* Returns file information
		* @return array File information
		*/
		public function file_info() {
			return $this -> f;
		}
		
		/**
		* @param int $notificationCode
		* @param int $severity
		* @param string $message
		* @param int $messageCode
		* @param int $bytesTransferred
		* @param int $bytesMax
		*/
		public function progress($notification_code, $severity, $message, $message_code, $bytes_transferred, $bytes_max) {
			$file_size = null;
			
			if(STREAM_NOTIFY_REDIRECTED === $notification_code) {
				echo "stream download is done.\n";
			}
			
			if(STREAM_NOTIFY_FILE_SIZE_IS === $notification_code) {
				$filesize = $bytes_max;
				echo "Filesize: ", $filesize, "\n";
			}
			
			if(STREAM_NOTIFY_PROGRESS === $notification_code) {
				$filesize = $bytes_max;
				
				if($bytes_transferred > 0) {
					$length = (int)(($bytes_transferred/$filesize)*100);
					//echo ($bytes_transferred/1024), $filesize/1024 . "kb\n";
					echo $length . "%\n";
				}
			}
			
			if(STREAM_NOTIFY_COMPLETED === $notification_code) {
				echo "bytes transfered is over: " . $bytes_transferred . "\n";
			}
		}
		
		private function recover_file_name($temp_file_path, $encode_file_name) {
			$file_arr = scandir($temp_file_path);
			$file_arr_len = count($file_arr);
			
			for($index=2;$index<$file_arr_len;$index++) {
				if($file_arr[$index] == $encode_file_name) {
					rename($encode_file_name, base64_decode($encode_file_name));
				}
			}
		}

	}

?>