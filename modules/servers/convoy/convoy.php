<?php

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use Illuminate\Database\Capsule\Manager as Capsule;

function convoy_GetFqdn(array $params)
{
    $fqdn = $params['serverhostname'];
    if ($fqdn === '') throw new Exception('Could not find the panel\'s FQDN - did you configure server group for the product?');

    foreach ([
        'DOT' => '.',
        'DASH' => '-',
    ] as $from => $to) {
        $fqdn = str_replace($from, $to, $fqdn);
    }

    // Assuming $params['serversecure'] holds the secure flag
    $protocol = $params['serversecure'] ? 'https://' : 'http://';
    if (ip2long($fqdn) !== false) $fqdn = 'http://' . $fqdn;
    else $fqdn = $protocol . $fqdn;

    return rtrim($fqdn, '/');
}

function convoy_API(array $params, $endpoint, array $data = [], $method = "GET", $dontLog = false) {
    $url = convoy_GetFqdn($params) . '/api/application/' . $endpoint;

    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($curl, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2);
    curl_setopt($curl, CURLOPT_USERAGENT, "Convoy-WHMCS");
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($curl, CURLOPT_POSTREDIR, CURL_REDIR_POST_301);
    curl_setopt($curl, CURLOPT_TIMEOUT, 5);

    $headers = [
        "Authorization: Bearer " . $params['serverpassword'],
        "Accept: Application/vnd.convoy.v1+json",
    ];

    if($method === 'POST' || $method === 'PATCH') {
        $jsonData = json_encode($data);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $jsonData);
        array_push($headers, "Content-Type: application/json");
        array_push($headers, "Content-Length: " . strlen($jsonData));
    }

    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($curl);
    $responseData = json_decode($response, true);
    $responseData['status_code'] = curl_getinfo($curl, CURLINFO_HTTP_CODE);

    if($responseData['status_code'] === 0 && !$dontLog) logModuleCall("Convoy-WHMCS", "CURL ERROR", curl_error($curl), "");

    curl_close($curl);

    if(!$dontLog) logModuleCall("Convoy-WHMCS", $method . " - " . $url,
        isset($data) ? json_encode($data) : "",
        print_r($responseData, true));

    return $responseData;
}

function convoy_Error($func, $params, Exception $err) {
    logModuleCall("Convoy-WHMCS", $func, $params, $err->getMessage(), $err->getTraceAsString());
}

function convoy_MetaData() {
    return [
        "DisplayName" => "Convoy",
        "APIVersion" => "1.0",
        "RequiresServer" => true,
    ];
}

function convoy_ConfigOptions() {
    return [
        "node_id" => [
            "FriendlyName" => "node_id",
            "Description" => "optional/required",
            "Type" => "text",
            "Size" => 10,
        ],
        "location_id" => [
            "FriendlyName" => "location_id",
            "Description" => "optional/required",
            "Type" => "text",
            "Size" => 10,
        ],
        "name" => [
            "FriendlyName" => "name",
            "Description" => "optional",
            "Type" => "text",
            "Size" => 10,
        ],
        "hostname" => [
            "FriendlyName" => "hostname",
            "Description" => "optional",
            "Type" => "text",
            "Size" => 10,
        ],
        "cpu" => [
            "FriendlyName" => "cpu",
            "Description" => "required",
            "Type" => "text",
            "Size" => 10,
        ],
        "memory" => [
            "FriendlyName" => "memory",
            "Description" => "required",
            "Type" => "text",
            "Size" => 10,
        ],
        "disk" => [
            "FriendlyName" => "disk",
            "Description" => "required",
            "Type" => "text",
            "Size" => 10,
        ],
        "snapshots" => [
            "FriendlyName" => "snapshots",
            "Description" => "optional",
            "Type" => "text",
            "Size" => 10,
        ],
        "backups" => [
            "FriendlyName" => "backups",
            "Description" => "optional",
            "Type" => "text",
            "Size" => 10,
        ],
        "bandwidth" => [
            "FriendlyName" => "bandwidth",
            "Description" => "optional",
            "Type" => "text",
            "Size" => 10,
        ],
        "account_password" => [
            "FriendlyName" => "account_password",
            "Description" => "optional",
            "Type" => "text",
            "Size" => 10,
        ],
        "template_uuid" => [
            "FriendlyName" => "template_uuid",
            "Description" => "required",
            "Type" => "text",
            "Size" => 10,
        ],
        "ipv4" => [
            "FriendlyName" => "ipv4",
            "Description" => "optional",
            "Type" => "text",
            "Size" => 10,
            "Default" => "0",
        ],
        "ipv6" => [
            "FriendlyName" => "ipv6",
            "Description" => "optional",
            "Type" => "text",
            "Size" => 10,
            "Default" => "0",
        ],
    ];
}

function convoy_TestConnection(array $params) {
    $solutions = [
        0 => "See the debug log of the module for a more detailed error.",
        401 => "The authorisation header is missing or not provided.",
        403 => "Recheck the password (which should be the application key).",
        404 => "Result not found.",
        422 => "Validation error.",
        500 => "Panel with error, check panel logs.",
    ];

    $err = "";
    try {
        $response = convoy_API($params, 'nodes');

        if($response['status_code'] !== 200) {
            $status_code = $response['status_code'];
            $err = "Invalid status code received: " . $status_code . ". Possible solutions: "
                . (isset($solutions[$status_code]) ? $solutions[$status_code] : "None.");
        } else {
            if($response['meta']['pagination']['count'] === 0) {
                $err = "Authentication successful, but no nodes available.";
            }
        }
    } catch(Exception $e) {
        convoy_Error(__FUNCTION__, $params, $e);
        $err = $e->getMessage();
    }

    return [
        "success" => $err === "",
        "error" => $err,
    ];
}

function random_($length) {
    if (class_exists("\Illuminate\Support\Str")) {
        return \Illuminate\Support\Str::random($length);
    } else if (function_exists("str_random")) {
        return str_random($length);
    } else {
        throw new \Exception("Cannot find a valid function to generate random strings.");
    }
}

function convoy_GenerateUsername($length = 8) {
    $returnable = false;
    while (!$returnable) {
        $generated = random_($length);
        if (preg_match('/[A-Z]+[a-z]+[0-9]+/', $generated)) {
            $returnable = true;
        }
    }
    return $generated;
}

function convoy_GeneratePassword($length = 8) {
    $returnable = false;
    while (!$returnable) {
        $generated = generateRandomPassword($length);
        if (preg_match('/[A-Z]+[a-z]+[0-9]+[^a-zA-Z0-9]+/', $generated)) {
            $returnable = true;
        }
    }
    return $generated;
}

function generateRandomPassword($length) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!@#$%^&*()-_+=<>?';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $password;
}

function checkPassword($password) {
    $uppercase = preg_match('/[A-Z]/', $password);
    $lowercase = preg_match('/[a-z]/', $password);
    $number = preg_match('/[0-9]/', $password);
    $specialChar = preg_match('/[^a-zA-Z0-9]/', $password);

    return $uppercase && $lowercase && $number && $specialChar;
}

function convoy_GetOption(array $params, $id, $default = NULL) {
    $options = convoy_ConfigOptions();

    $friendlyName = $options[$id]['FriendlyName'];
    if(isset($params['configoptions'][$friendlyName]) && $params['configoptions'][$friendlyName] !== '') {
        return $params['configoptions'][$friendlyName];
    } else if(isset($params['configoptions'][$id]) && $params['configoptions'][$id] !== '') {
        return $params['configoptions'][$id];
    } else if(isset($params['customfields'][$friendlyName]) && $params['customfields'][$friendlyName] !== '') {
        return $params['customfields'][$friendlyName];
    } else if(isset($params['customfields'][$id]) && $params['customfields'][$id] !== '') {
        return $params['customfields'][$id];
    }

    $found = false;
    $i = 0;
    foreach(convoy_ConfigOptions() as $key => $value) {
        $i++;
        if($key === $id) {
            $found = true;
            break;
        }
    }

    if($found && isset($params['configoption' . $i]) && $params['configoption' . $i] !== '') {
        return $params['configoption' . $i];
    }

    return $default;
}

function convoy_CreateAccount(array $params) {
    try {
        $serverId = convoy_GetServerID($params);
        if(isset($serverId)) throw new Exception('The server could not be created because it is already created.');
        $password = '';
        $userResult = convoy_API($params, 'users?filter[email]=' . urlencode($params['clientsdetails']['email']));
        if($userResult['meta']['pagination']['total'] === 0) {
            $username = convoy_GenerateUsername();
            $password = convoy_GeneratePassword();
            if($params['username'] != '') $username = $params['username'];
            if(checkPassword($params['password'])) $password = $params['password'];
            $userResult = convoy_API($params, 'users', [
                'root_admin' => false,
                'name' => $username,
                'email' => $params['clientsdetails']['email'],
                'password' => $password,
            ], 'POST');
        }
        $userResult = convoy_API($params, 'users?filter[email]=' . urlencode($params['clientsdetails']['email']));
        if($userResult['meta']['pagination']['total'] > 0) {
            foreach($userResult['data'] as $key => $value) {
                if($value['email'] === $params['clientsdetails']['email']) {
                    $userResult = array_merge($userResult, $value);
                    $username = $value['name'];
                    break;
                }
                $userResult = array_merge($userResult, $userResult['data'][0]);
            }
        } else {
            throw new Exception('Could not create user.');
        }

        if($userResult['status_code'] === 200 || $userResult['status_code'] === 201) {
            $user_id = $userResult['id'];
        } else {
            throw new Exception('Could not create user, received an error code:' . $userResult['status_code'] . '. Enable module debug logging for more information.');
        }
        
        $node_id = (int) convoy_GetOption($params, 'node_id', '0');
        $location_id = (int) convoy_GetOption($params, 'location_id', '0');
        if($node_id <= 0) {
            $nodes = convoy_API($params, 'nodes');
            if($location_id > 0) $nodes = convoy_API($params, 'nodes?filter[location_id]='.$location_id);
            if($nodes['meta']['pagination']['total'] === 0) {
                throw new Exception('Could not create the server, could not find a node to assign.');
            } else {
                $nodes_ids = array();
                foreach($nodes['data'] as $key => $value) {
                    array_push($nodes_ids, array($value['memory']-$value['memory_allocated'], $value['id']));
                }
                rsort($nodes_ids);
                $node_id = $nodes_ids[0][1];
            }
        } else {
            $nodes = convoy_API($params, 'nodes/'.$node_id);
            if($nodes['status_code'] === 200 || $nodes['status_code'] === 201) {
                if($location_id > 0) {
                    if($nodes['location_id'] != $location_id) throw new Exception('The server could not be created, the node location does not match the one specified.');
                }
            } else {
                throw new Exception('Could not find the node, received an error code:' . $userResult['status_code'] . '. Enable module debug logging for more information.');  
            }
        }
        $name = convoy_GetOption($params, 'name', convoy_GenerateUsername());
        $hostname = convoy_GetOption($params, 'hostname', convoy_GenerateUsername());
        if($params['domain'] != '') $hostname = $params['domain'];
        $cpu = convoy_GetOption($params, 'cpu');
        $memory = (int) convoy_GetOption($params, 'memory');
        $disk = (int) convoy_GetOption($params, 'disk');
        $snapshots = convoy_GetOption($params, 'snapshots', '0');
        $backups = convoy_GetOption($params, 'backups', '0');
        $bandwidth = convoy_GetOption($params, 'bandwidth', '0');
        $account_password = convoy_GetOption($params, 'account_password', convoy_GeneratePassword());
        $template_uuid = convoy_GetOption($params, 'template_uuid');
        $ipv4 = (int) convoy_GetOption($params, 'ipv4', '0');
        $ipv6 = (int) convoy_GetOption($params, 'ipv6', '0');
        $dedicatedip = '';
        $assignedips = array();

        $address_ids = array();
        if($ipv4 > 0) {
            $response = convoy_API($params, 'nodes/'.$node_id.'/addresses?filter[type]=ipv4');
            if($response['meta']['pagination']['total'] === 0) {
                throw new Exception('Could not create the server, could not find an ipv4 to assign.');
            } else {
                foreach($response['data'] as $key => $value) {
                    if($value['server_id'] == null) {
                        array_push($address_ids, $value['id']);
                        if($dedicatedip === '') $dedicatedip = $value['address'];
                        array_push($assignedips, $value['address']);
                        $ipv4--;
                    }
                    if($ipv4 == 0) break;
                }
            }
        }
        if($ipv6 > 0) {
            $response = convoy_API($params, 'nodes/'.$node_id.'/addresses?filter[type]=ipv6');
            if($response['meta']['pagination']['total'] === 0) {
                throw new Exception('Could not create the server, could not find an ipv6 to assign.');
            } else {
                foreach($response['data'] as $key => $value) {
                    if($value['server_id'] == null) {
                        array_push($address_ids, $value['id']);
                        $ipv6--;
                    }
                    if($ipv6 == 0) break;
                }
            }
        }
        if($ipv4 > 0) {
            throw new Exception('Could not create the server, could not find an ipv4 to assign.');
        }
        if($ipv6 > 0) {
            throw new Exception('Could not create the server, could not find an ipv6 to assign.');
        }
        $one = 0;
        foreach($assignedips as $key => $value) {
            if($one == 0) {
                $assignedips_ = $value;
                $one = 1;
            } else $assignedips_ = $assignedips_ . ',' . $value;
        }
        
        $serverData = [
            'node_id' => (int) $node_id,
            'user_id' => (int) $user_id,
            'name' => $name,
            'hostname' => $hostname,
            'vmid' => null,
            'limits' => [
                'cpu' => (int) $cpu,
                'memory' => ($memory * 1048576),
                'disk' => ($disk * 1048576),
                'snapshots' => (int) $snapshots,
                'backups' => (int) $backups,
                'bandwidth' => (int) $bandwidth,
                'address_ids' => $address_ids,
            ],
            'account_password' => $account_password,
            'should_create_server' => true,
            'template_uuid' => $template_uuid,
            'start_on_completion' => true
        ];

        $server = convoy_API($params, 'servers', $serverData, 'POST');

        if($server['status_code'] === 400) throw new Exception('No node could be found that satisfies the request....');
        if($server['status_code'] !== 201 && $server['status_code'] !== 200) throw new Exception('The server could not be created, received the error code: ' . $server['status_code'] . '. Enable module debug logging for more information.');

        $res = Capsule::table('tblcustomfields')->where('relid', $params['pid'])->where('fieldname', 'server_id')->first(); 
        Capsule::table('tblcustomfieldsvalues')->insert([
            'fieldid' => $res->id,
            'relid' => $params['serviceid'],
            'value' => $server['data']['id']
        ]);
        $res = Capsule::table('tblcustomfields')->where('relid', $params['pid'])->where('fieldname', 'serverpass')->first(); 
        Capsule::table('tblcustomfieldsvalues')->insert([
            'fieldid' => $res->id,
            'relid' => $params['serviceid'],
            'value' => $account_password
        ]);
        $result = localAPI('EncryptPassword', array('password2' => $password));
        $password = $result['password'];
        Capsule::table('tblhosting')->where('id', $params['serviceid'])->update([
            'username' => $username,
            'password' => $password,
            'dedicatedip' => $dedicatedip,
            'assignedips' => $assignedips_,
        ]);
        
        $params['username'] = $username;
        $params['password'] = $password;
        $params['customfields']['serverpass'] = $account_password;
    } catch(Exception $err) {
        return $err->getMessage();
    }

    return 'success';
}

function convoy_GetServerID(array $params, $raw = false) {
    $serverResult = convoy_API($params, 'servers/' . $params['customfields']['server_id'], [], 'GET', true);
    if($serverResult['status_code'] === 200) {
        if($raw) return $serverResult;
        else return $serverResult['data']['id'];
    } else if($serverResult['status_code'] === 500) {
        throw new Exception('Could not get the server, the panel had an error. See the panel logs for more information.');
    }
}

function convoy_SuspendAccount(array $params) {
    try {
        $serverId = convoy_GetServerID($params);
        if(!isset($serverId)) throw new Exception('The server could not be suspended because it does not exist.');

        $suspendResult = convoy_API($params, 'servers/' . $serverId . '/settings/suspend', [], 'POST');
        if($suspendResult['status_code'] !== 204) throw new Exception('Could not suspend the server, received an error code: ' . $suspendResult['status_code'] . '. Enable module debug logging for more information.');
    } catch(Exception $err) {
        return $err->getMessage();
    }

    return 'success';
}

function convoy_UnsuspendAccount(array $params) {
    try {
        $serverId = convoy_GetServerID($params);
        if(!isset($serverId)) throw new Exception('The server could not be reactivated because it does not exist.');

        $suspendResult = convoy_API($params, 'servers/' . $serverId . '/settings/unsuspend', [], 'POST');
        if($suspendResult['status_code'] !== 204) throw new Exception('Could not reactivate the server, received an error code: ' . $suspendResult['status_code'] . '. Enable module debug logging for more information.');
    } catch(Exception $err) {
        return $err->getMessage();
    }

    return 'success';
}

function convoy_TerminateAccount(array $params) {
    try {
        $serverId = convoy_GetServerID($params);
        if(!isset($serverId)) throw new Exception('The server could not be terminated because it does not exist.');

        $deleteResult = convoy_API($params, 'servers/' . $serverId, [], 'DELETE');
        if($deleteResult['status_code'] !== 204) throw new Exception('Could not terminate the server, received an error code: ' . $deleteResult['status_code'] . '. Enable module debug logging for more information.');
    } catch(Exception $err) {
        return $err->getMessage();
    }

    return 'success';
}

function convoy_ChangePackage(array $params) {
    try {
        $serverRaw = convoy_GetServerID($params, true);
        $serverId = $serverRaw['data']['id'];
        if(!isset($serverId)) throw new Exception('The server cannot be updated because it does not exist.');

        $node_id = $serverRaw['data']['node_id'];
        $cpu = convoy_GetOption($params, 'cpu', $serverRaw['data']['limits']['cpu']);
        $memory = (int) convoy_GetOption($params, 'memory', ($serverRaw['data']['limits']['memory'] / 1048576));
        $disk = (int) convoy_GetOption($params, 'disk', ($serverRaw['data']['limits']['disk'] / 1048576));
        $snapshots = convoy_GetOption($params, 'snapshots', $serverRaw['data']['limits']['snapshots']);
        $backups = convoy_GetOption($params, 'backups', $serverRaw['data']['limits']['backups']);
        $bandwidth = convoy_GetOption($params, 'bandwidth', $serverRaw['data']['limits']['bandwidth']);
        $ipv4 = convoy_GetOption($params, 'ipv4', count($serverRaw['data']['limits']['addresses']['ipv4']));
        $ipv6 = convoy_GetOption($params, 'ipv6', count($serverRaw['data']['limits']['addresses']['ipv6']));
        $address_ids = array();

        $res = Capsule::table('tblhosting')->where('id', $params['serviceid'])->first();
        $dedicatedip = $res->dedicatedip;

        $ipv4Data = $serverRaw['data']['limits']['addresses']['ipv4'];
        $ipv6Data = $serverRaw['data']['limits']['addresses']['ipv6'];
        if(count($ipv4Data) > 0 && $ipv4 > 0) {
            foreach($ipv4Data as $key => $value) {
                if($value['address'] == $dedicatedip) {
                    array_push($address_ids, $value['id']);
                    $ipv4--;
                    if($ipv4 == 0) break;
                }
            }
        }
        if(count($ipv4Data) > 0 && $ipv4 > 0) {
            foreach($ipv4Data as $key => $value) {
                if($value['address'] != $dedicatedip) {
                    array_push($address_ids, $value['id']);
                    $ipv4--;
                    if($ipv4 == 0) break;
                }
            }
        }
        if(count($ipv6Data) > 0 && $ipv6 > 0) {
            foreach($ipv6Data as $key => $value) {
                array_push($address_ids, $value['id']);
                $ipv6--;
                if($ipv6 == 0) break;
            }
        }
        if($ipv4 > 0) {
            $response = convoy_API($params, 'nodes/'.$node_id.'/addresses?filter[type]=ipv4');
            if($response['meta']['pagination']['total'] === 0) {
                throw new Exception('Could not create the server, could not find an ipv4 to assign.');
            } else {
                foreach($response['data'] as $key => $value) {
                    if($value['server_id'] == null) {
                        array_push($address_ids, $value['id']);
                        $ipv4--;
                    }
                    if($ipv4 == 0) break;
                }
            }
        }
        if($ipv6 > 0) {
            $response = convoy_API($params, 'nodes/'.$node_id.'/addresses?filter[type]=ipv6');
            if($response['meta']['pagination']['total'] === 0) {
                throw new Exception('Could not create the server, could not find an ipv6 to assign.');
            } else {
                foreach($response['data'] as $key => $value) {
                    if($value['server_id'] == null) {
                        array_push($address_ids, $value['id']);
                        $ipv6--;
                    }
                    if($ipv6 == 0) break;
                }
            }
        }
        if($ipv4 > 0) {
            throw new Exception('Could not create the server, could not find an ipv4 to assign.');
        }
        if($ipv6 > 0) {
            throw new Exception('Could not create the server, could not find an ipv6 to assign.');
        }
        
        $assignedips = array();
        foreach($address_ids as $key => $value) {
            $response = convoy_API($params, 'nodes/'.$node_id.'/addresses?filter[type]=ipv4');
            foreach($response['data'] as $key_ => $value_) {
                if($value_['id'] == $value) {
                    array_push($assignedips, $value_['address']);
                }
            }
        }
        $one = 0;
        foreach($assignedips as $key => $value) {
            if($one == 0) {
                $assignedips_ = $value;
                $one = 1;
            } else $assignedips_ = $assignedips_ . ',' . $value;
        }
        Capsule::table('tblhosting')->where('id', $params['serviceid'])->update([
            'assignedips' => $assignedips_,
        ]);
        
        $updateData = [
            'address_ids' => $address_ids,
            'snapshot_limit' => (int) $snapshots,
            'backup_limit' => (int) $backups,
            'bandwidth_limit' => (int) $bandwidth,
            'bandwidth_usage' => $serverRaw['data']['usages']['bandwidth'],
            'cpu' => (int) $cpu,
            'memory' => (int) ($memory * 1048576),
            'disk' => (int) ($disk * 1048576)
        ];

        $updateResult = convoy_API($params, 'servers/' . $serverId . '/settings/build', $updateData, 'PATCH');
        if($updateResult['status_code'] !== 200) throw new Exception('Failed to update build of the server, received error code: ' . $updateResult['status_code'] . '. Enable module debug log for more info.');
    } catch(Exception $err) {
        return $err->getMessage();
    }
    return 'success';
}

function convoy_ClientArea(array $params) {
    if($params['moduletype'] !== 'convoy') return;

    try {
        $fqdn = convoy_GetFqdn($params);
        $serverData = convoy_GetServerID($params, true);
        if($serverData['status_code'] === 404 || !isset($serverData['data']['id'])) return [
            'templatefile' => 'clientarea',
            'vars' => [
                'serviceurl' => $fqdn,
            ],
        ];

        return [
            'templatefile' => 'clientarea',
            'vars' => [
                'serviceurl' => $fqdn . '/servers/' . $serverData['data']['id'],
            ],
        ];
    } catch (Exception $err) {
        // Ignore
    }
}
