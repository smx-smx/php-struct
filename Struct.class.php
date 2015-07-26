<?php
/*
	Copyright (C) 2015 Smx
*/
define("int8_t"  , 'c');
define("uint8_t" , 'C');
define("int16_t" , 's');
define("uint16_t", 'S');
define("int32_t" , 'l');
define("uint32_t", 'L');
define("int64_t" , 'q');
define("uint64_t", 'Q');

define("uint16_le_t", "v");
define("uint16_be_t", "n");
define("uint32_le_t", "V");
define("uint32_be_t", "N");
define("uint64_le_t", "P");
define("uint64_be_t", "J");

define("float_t", "f");
define("double_t", "d");

/* Returns the data as int type */
define("VAR_NUMERIC", 1 << 0);
/* Returns the data as double type */
define("VAR_DOUBLE", 1 << 1);
/* Returns the data as float type */
define("VAR_FLOAT", 1 << 2);
/* Use this field for dynamic string sizes or header sizes. 
   This field overrides the size of the next member with the value read from the file*/
define("FLAG_STRSZ", 1 << 3);
/* Converts all read bytes to printable characters (when possible)*/
define("VAR_STRING", 1 << 4);
define("ENDIAN_LITTLE", 1 << 5);
define("ENDIAN_BIG", 1 << 6);

class StructMember {
	const ENDIAN_LITTLE = 4321;
	const ENDIAN_BIG = 1234;
	public function __construct($name, $type, $count=1, $flags=null){
		$this->name = $name;
		$this->type = $type;
		$this->count = $count;
		$this->decodeAs = null;
		$this->data = "";
		if(!$this->isSubStruct()){
			if(is_null($flags)){
				$this->endianess = self::getMachineEndianess();
			} else {
				if(($flags & ENDIAN_BIG) == ENDIAN_BIG){
					$this->endianess = self::ENDIAN_BIG;
					$this->type = self::reverseType($type);
					$flags = $flags & ~ENDIAN_BIG;
				}
				if(($flags & ENDIAN_LITTLE) == ENDIAN_LITTLE){
					$this->endianess = self::ENDIAN_LITTLE;
					$this->type = self::reverseType($type);
					$flags = $flags & ~ENDIAN_LITTLE;
				}
				$this->decodeAs = $flags;
			}
		}
	}
	public function isByte(){
		return $this->sizeof() == 1;
	}
	public function isShort(){
		return $this->sizeof() == 2;
	}
	public function isLong(){
		return $this->sizeof() == 4;
	}
	public function isLongLong(){
		return $this->sizeof() == 8;
	}
	public function isSubStruct(){
		return
			is_object($this->getType()) &&
			$this->getType() instanceof Struct;
	}
	public function sizeof(){
		$sz = 0;
		if($this->isSubStruct()){
			return $this->getType()->getSize();
		}
		return strlen(pack($this->getType(), "123"));
	}
	public function getSingleSize(){ return $this->sizeof(); }
	public function getSize(){ return $this->sizeof() * $this->getCount(); }
	public function getName(){ return $this->name; }
	public function getEndianess(){ return $this->endianess; }
	public function getType(){ return $this->type; }
	public function getFlags(){ return $this->decodeAs; }

	public function setType($type){ $this->type = $type; }
	public function setCount($count){ $this->count = $count; }
	public function setData($data){ $this->data = $data; }

	public function getElement($idx, $glue=null){
		if($idx > $this->getCount()-1){
			return false;
		}

		$part = unpack($this->getType(),
				substr($this->data, $idx * $this->sizeof())
			);
		return (is_null($glue)) ? $part : implode($glue, $part);
	}
	
	public function setElement($idx, $data){
		if($idx > $this->getCount()-1){
			return false;
		}

		if($this->isSubStruct()){
			$this->data[$idx] = $data;
		} else {
			/*printf("Setting %d-%d data (%d)\n",
				$idx * $this->sizeof(),
				($idx + 1) * $this->sizeof(),
				$idx);*/

			$before = substr($this->data, 0, $idx * $this->sizeof());
			if($this->getCount() > 1)
				$after = substr($this->data, ($idx + 1) * $this->sizeof());
			else
				$after = "";

			$this->data = $before . pack($this->getType(), $data) . $after;
		}
		return true;
	}

	/* Packs the data into the field */
	public function setValue($data){
		for($i=0; $i < $this->getCount(); $i++){
			$this->setElement($i, $data);
		}
	}

	/* Unpacks the binary values read with apply() */
	public function getValue(){
		/* If we have a substruct, return the substructs */
		if($this->isSubStruct()){
			return $this->data;
		}

		if($this->decodeAs == VAR_STRING){
			$data = "";
			for($i=0; $i<$this->getCount(); $i++)
				$data .= chr($this->getElement($i, ""));
			return $data;
		} else {
			$data = array();
			for($i=0; $i<$this->getCount(); $i++)
				array_push($data, $this->getElement($i, ""));

			if(count($data) == 1){ //we only have 1 element
				if(($this->decodeAs & VAR_NUMERIC) == VAR_NUMERIC)
					return intval($data[0]);
				elseif(($this->decodeAs & VAR_FLOAT) == VAR_FLOAT)
					return unpack(float_t, $data[0]);
				elseif(($this->decodeAs & VAR_DOUBLE) == VAR_DOUBLE)
					return unpack(double_t, $data[0]);
				else
					return $data[0];
			}
			return $data; //array of bytes
		}
	}
	public function getCount(){ return $this->count; }
	public function byteswap($endian=null){
		if(is_null($this->data)){
			throw new Exception("Data not loaded yet!");
		}
		$value = $this->getValue();
		if($this->isByte())
			return $value;
		if($this->isShort()){
			if($this->getEndianess() == self::ENDIAN_LITTLE)
				return pack(uint16_be_t, $value);
			else
				return pack(uint16_le_t, $value);
		}
		if($this->isLong()){
			if($this->getEndianess() == self::ENDIAN_LITTLE)
				return pack(uint32_be_t, $value);
			else
				return pack(uint32_le_t, $value);
		}
		if($this->isLongLong()){
			if($this->getEndianess() == self::ENDIAN_LITTLE)
				return pack(uint64_be_t, $value);
			else
				return pack(uint64_le_t, $value);
		}
	}
	public static function typeValid($type){
		return
			$type == int8_t || $type == uint8_t ||
			$type == int16_t || $type == uint16_t ||
			$type == uint16_le_t || $type = uint16_be_t ||
			$type == int32_t || $type == uint32_t ||
			$type == uint32_be_t || $type == uint32_le_t ||
			$type = int64_t || $type = uint64_t ||
			$type == uint64_le_t || $type == uint64_be_t;
	}
	/* Swaps endianess for a pack specifier */
	public static function reverseType($type){
		$mach = self::getMachineEndianess();
		switch($type){
			case int8_t:
			case uint8_t:
				return $type;
				break;
			case int16_t:
			case uint16_t:
				return ($mach == self::ENDIAN_LITTLE) ? uint16_be_t : uint16_le_t;
				break;
			case int32_t:
			case uint32_t:
				return ($mach == self::ENDIAN_LITTLE) ? uint32_be_t : uint32_le_t;
				break;
			case int64_t:
			case uint64_t:
				return ($mach == self::ENDIAN_LITTLE) ? uint64_be_t : uint64_le_t;
				break;
			default:
				return $type;
				break;
		}
	}
	public static function getMachineEndianess(){
		$val = 1234;
		$test = pack(uint32_t, $val);
		$big = array_values(unpack(uint32_be_t, $test))[0];
		return ($val == $big) ? self::ENDIAN_BIG : self::ENDIAN_LITTLE;
	}
}

class ArgException extends Exception {
	public function __construct(){
		$argv = func_get_args();
		$message = vsprintf(array_shift($argv), $argv);
		parent::__construct($message, 0, null);
	}

	public function __toString() {
		return $this->message;
	}
}

class Struct {
	public $members = array();
	public function __construct(){
		$argc = func_num_args();
		$argv = func_get_args();
		/* Parse the vararg list */
		for($i=0; $i<$argc; ){
			if(!is_string( ($name = $argv[$i++]) )){ //name
				throw new ArgException("Invalid Arguments: Expected member name, got '".$name."'");
			}
			//print(">> NAME: ".$name.PHP_EOL);
			if($i >= $argc){
				throw new ArgException("Missing type for '%s'", $name);
			}
			if(!is_object( ($type = $argv[$i++]) ) && !$type instanceof Struct){
				if(!is_string($type) || strlen($type) != 1){ //type
					throw new ArgException("Invalid Arguments: Expected member type, got '".$type."'");
				}
				if(!StructMember::typeValid($type)){
					throw new ArgException("Invalid type %c", $type);
				}
			}
			//print(">> TYPE: ".$type.PHP_EOL);
			if($i >= $argc || !is_numeric( ($count = $argv[$i]) )){ //count
				$this->members[$name] = new StructMember(
					$name, $type
				);
				continue;
			}
			$i++;
			//print(">> COUNT: ".$count.PHP_EOL);
			if($i >= $argc || !is_numeric( ($flags = $argv[$i]) )){ //endianess
				$this->members[$name] = new StructMember(
					$name, $type, $count
				);
				continue;
			}
			$i++;
			//print(">> FLAGS: ".$flags.PHP_EOL);
			$this->members[$name] = new StructMember(
				$name, $type, $count, $flags
			);
		}
	}
	public function getPackString(){
		$str = "";
		foreach($this->members as $memb){
			$str.=$memb->getType();
		}
		return $str;
	}
	
	public function getSize(){
		$sz = 0;
		foreach($this->members as $memb){
			$sz += $memb->getSize();
		}
		return $sz;
	}

	public function __clone(){
		foreach($this->members as $name => &$member){
			$this->members[$name] = clone($member);
		}
	}

	public function getData(){
		$data = "";
		foreach($this->members as $name => $member){
			if($member->isSubStruct()){
				foreach($member->getValue() as $subStruct){
					$data .= $subStruct->getData();
				}
			} else {
				$data .= $member->data;
			}
		}
		return $data;
	}

	/* Reads binary data $data into the members of the struct */
	public function apply($data){
		$i = 0;
		foreach($this->members as $name => &$memb){
			/* If we have a substruct, recursively call apply on it */
			if($memb->isSubStruct()){
				/* Create an array of structures as the binary data */
				$memb->data = array();
				for($i=0; $i<$memb->getCount(); $i++){
					/* Clone the structure and its members */
					$subStruct = clone($memb->getType());
					/* Fill it */
					$data = $subStruct->apply($data);
					/* Insert it */
					$memb->data[$i] = $subStruct;
					//printf("Substruct insert -> %s[%d]\n", $memb->name, $i);
					/*foreach($subStruct->members as $n => $m){
						printf("%s => 0x%x\n", $n, $m->getValue());
						//var_dump($m);
					}*/
				}
			} else {
				/* Read the binary data represented by the member */
				$totalSz = $memb->sizeof() * $memb->getCount();
				$memb_data = substr($data, 0, $totalSz);

				/* Assign the read data and increment position */
				$memb->data = $memb_data;
				$data = substr($data, $totalSz);

				/* Handle dynamic strings */
				if(($memb->getFlags() & FLAG_STRSZ) == FLAG_STRSZ){
					/* Update type and size  of the next member 
						depending on the value we just read */
					$nextStr = &array_values($this->members)[$i+1];
					$nextStr->setType(int8_t);
					$nextStr->setCount($memb->getValue());
				}
			}
			$i++;
		}
		/* Return the remaining data */
		return $data;
	}

	public static function isStructArray($arr){
		return is_array($arr) && count($arr) > 0 && $arr[0] instanceof Struct;
	}

	public static function printStruct($struct){
		foreach($struct->members as $name => $memb){
			$value = $memb->getValue();
			if(is_array($value)){
				if(count($value) > 0 && $value[0] instanceof Struct){
					foreach($value as $subStruct){
						self::printStruct($subStruct);
					}
				} else {
					printf("%s\n", $name);
					var_dump($value);
				}
			} else {
				printf("%s => 0x%x\n", $name, $value);
			}
		}
	}
}
?>
