<?php 

namespace app\telegram\traits;


trait PrefixTrait 
{

	public function hasPrefix(string $data, string $prefix) 
	{
		$dataBeginning = mb_substr($data, 0, mb_strlen($prefix));
		return ($dataBeginning == $prefix);
	}


	public function getSuffix(string $data, string $prefix) 
	{
		return mb_substr($data, mb_strlen($prefix));
	}
}