<?php

	class zip_progress {
		public function __construct($file_path) {
			$this->zip = new ZipArchive();
			$this->file_path = $file_path;
			
			if(file_exists($this->file_path)) {
				@unlink($this->file_path);
			}
				
			if ($this->zip->open($this->file_path, ZipArchive::CREATE)!==TRUE) {
				exit("cannot open " . $this->file_path);
			}
		}
		
		public function add_file_from_path($file_path, $file_name) {
			$this->zip->addFile($file_path, $file_name);
		}
		
		public function close_zip() {
			$this->zip->close();
		}
	}
?>