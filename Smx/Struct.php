<?php
/*
	Copyright (C) 2019 Stefano Moioli <smxdev4@gmail.com>
*/
namespace Smx;

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

	public function getOffset($name){
		$off = 0;
		foreach($this->members as $mbname => $memb){
			if($mbname == $name){
				break;
			}
			$off += $memb->getSize();
		}
		return $off;
	}

	public function __clone(){
		foreach($this->members as $name => &$member){
			$this->members[$name] = clone($member);
		}
	}
	
	public function getValue($name){
		$val = $this->members[$name]->getValue();
		if(Struct::isStructArray($val) && count($val) == 1)
			return $val[0];
		return $val;
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
					print($name . PHP_EOL);
					var_dump($value);
				}
			} else {
				printf("{$name} => 0x%x\n", $value);
			}
		}
	}
}
