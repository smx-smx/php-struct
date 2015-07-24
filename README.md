# php-struct
Struct implementation in PHP. It supports decoding binary files.

Creating a new struct
```php
$myStruct_t = new Struct(
  "foo", uint32_t,        //single uint32_t
  "baz", uint8_t, 30      //array of 30 unsigned chars
);
```
You can specify flags for a members, such as:
```php
$myStruct_t = new Struct(
  "beval", uint32_t, 1, ENDIAN_BIG,       //big endian value
  "leval", uint32_t, 1, ENDIAN_LITTLE,    //little endian value
  "myString", int8_t, 32, VAR_STRING,     //string of 32 characters
  "myNumber", uint32_t, 1, VAR_NUMERIC,   //explicitly uses the PHP's int type
);
```

You can use FLAG_STRSZ to indicate that this member will specify the size of an upcoming string
```php
$string_t = new Struct(
  "strSz", uint32_t, 1, FLAG_STRSZ,   //a string follow, and its length is indicated by this member
  "varString", uint8_t, 0,            //the size will be replaced at runtime due to FLAG_STRSZ
);
```
The use of FLAG_STRSZ makes a structure size unpredictable.
<br><br>

You can also use nested structures:
```php
$myStruct_t = new Struct(
  "foo", uint32_t,        //single uint32_t
  "baz", uint8_t, 30      //array of 30 unsigned chars
);

$otherStruct_t = new Struct(
  "magic", uint8_t, 4,
  "elements", $myStruct_t, 32, //creates an array of 32 structures
);
```

Structs and files:
```php
// Clone the structure template
$header = clone($header_t);
// Simple check for proper arguments
if($argc < 2 || !file_exists($argv[1])){
	fprintf(STDERR, "File not found!\n");
	return 1;
}
// Open the specified file in read mode
$f = fopen($argv[1], "rb");
// Get enough data to fill the structure
$d = fread($f, $header->getSize());
// We don't need the file anymore
fclose($f);

// Put the data we read into the structure
$header->apply($d);
```

Parsing the elements
```php
printf("Struct size: %d\n", $header->getSize());
foreach($header->members as $name => $member){
  printf("member '%s', value: 0x%x\n", $name, $member->getValue());
}
```

And for nested structures?
```php
function printStruct($struct){
	foreach($struct->members as $name => $memb){
		$value = $memb->getValue();
		if(is_array($value)){
			if(count($value) > 0 && $value[0] instanceof Struct){
				foreach($value as $subStruct){
					printStruct($subStruct);
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
```

Getting the binary data of a member
```php
$binaryData = $member->data;
```

Setting the binary data of a member
```php
$member->setData($binData);
```

Getting the decoded value of a member (according to its type)
```php
$value = $member->getValue();
```

Setting the value of a member (will get encoded according to its type)
```php
$member->setValue($numberORString);
```
