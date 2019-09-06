<?php
/**
 * Copyright (C) 2019 Stefano Moioli <smxdev4@gmail.com>
 */
namespace Smx;

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
		
		$part = unpack($this->getType(), substr($this->data, $idx * $this->sizeof()));
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
			for($i=0; $i<$this->getCount(); $i++){
				$type = $this->getType();
				// Get neutral type
				switch($type){
					case uint16_le_t:
					case uint16_be_t:
						$type = int16_t;
						break;
					case uint32_be_t:
					case uint32_le_t:
						$type = uint32_t;
						break;
					case uint64_be_t:
					case uint64_le_t:
						$type = uint64_t;
						break;
				}
				
				$value = pack($type, $this->getElement($i, ""));
				$data .= $value;
			}
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
		switch($type){
			case int8_t:
			case uint8_t:
			case int16_t:
			case uint16_t:
			case uint16_le_t:
			case uint16_be_t:
			case int32_t:
			case uint32_t:
			case int64_t:
			case uint64_t:
			case uint64_le_t:
			case uint64_be_t:
				return true;
			default:
				return false;
		}
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