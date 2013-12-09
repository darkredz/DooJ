<?php
/**
 * DooJsonSchema class file.
 *
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @link http://www.doophp.com/
 * @copyright Copyright &copy; 2009-2013 Leng Sheng Hong
 * @license http://www.doophp.com/license-v2
 */

/**
 * A helper class that convert a defined set of field data which is used in DooValidator to JSON schema.
 *
 * <p>Example definition of fields data. By default, fields are required unless 'optional' is specified. Rules are defined based on the validation method's parameters in DooValidator.</p>
 * <code>
 * $fields = [
 *     'name' => [
 *         'type' => 'string',
 *         'rules' => [
 *             ['minLength',3],
 *             ['maxLength',40],
 *             ['alphaSpace']
 *         ],
 *     ],
 *     'age' => [
 *         'type' => 'integer',
 *         'rules' => [
 *             ['min',3],
 *             ['max',20]
 *         ],
 *     ],
 *     'money' => [
 *         'type' => 'number',
 *         'rules' => [
 *             ['min',10],
 *             ['max',2000]
 *         ],
 *     ],
 *     'title' => [
 *         'type' => 'string',
 *         'rules' => [
 *             ['minLength',6],
 *             ['maxLength',60],
 *             ['optional']
 *         ],
 *     ],
 *     'creators' => [
 *         'type' => 'string',
 *     ],
 *     'hobbies' => [
 *         'type' => 'string',
 *
 *         //inList creates enum on the property in JSON schema
 *         'rules' => [
 *             ['inList', ['swimming','drawing','reading','jogging']]
 *         ],
 *     ]
 * ];
 * </code>
 *
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @package doo.helper
 * @since 2.0
 */
class DooJsonSchema {

    /**
     * @param array $fieldData Field definition data
     * @return array|string converted JSON schema
     */
    public static function convert($fieldData, $toJson=false){
        $schemaProp = [];

        foreach($fieldData as $fname => $p){
            $schemaProp[$fname] = [
                'title' => ucfirst($fname),
                'type' => $p[0],
                'required' => true,
            ];

            if(isset($p[2])){
                $schemaProp[$fname]['description'] = $p[2];
            }

            if($p[1]){
                foreach($p[1] as $rule){
                    if($rule=='optional' || $rule[0]=='optional'){
                        $schemaProp[$fname]['required'] = false;
                    }
                    else{
                        switch($rule[0]){
                            case 'inList':
                                $schemaProp[$fname]['enum'] = $rule[1]; break;
                            case 'min':
                                $schemaProp[$fname]['minimum'] = $rule[1]; break;
                            case 'max':
                                $schemaProp[$fname]['maximum'] = $rule[1]; break;
                            case 'minLength':
                                $schemaProp[$fname]['minLength'] = $rule[1]; break;
                            case 'maxLength':
                                $schemaProp[$fname]['maxLength'] = $rule[1]; break;
                        }
                    }
                }
            }
        }

        $schema = [
            'type' => 'object',
            'properties' => $schemaProp
        ];

        if($toJson===true){
            return json_encode($schema);
        }

        return $schema;
    }
}