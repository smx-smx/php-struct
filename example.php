<?php
/**
 * Copyright (C) 2019 Stefano Moioli <smxdev4@gmail.com>
 */
spl_autoload_register();

use Smx\Struct;

$inner = new Struct(
	"bar", uint32_t, 2,
	"baz", uint16_t, 2,
	"foo", uint8_t, 1
);

$st = new Struct(
	"bar", $inner, 10
);

// or read from file, or use pack()
$data = str_repeat("\x02\x00\x00\x00", 2);
$data.= str_repeat("\xfe\xff", 2);
$data.= "\x00";
$data = str_repeat($data, 10);

$st->apply($data);

Struct::printStruct($st);