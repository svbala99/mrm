<?php

header("Cache-Control: no-transform,public,max-age=300,s-maxage=900");
header('Content-Type: application/json');

function checkReqMethod()
{
    $method = $_SERVER['REQUEST_METHOD'];
    if ($method == 'POST') {
        return "POST";
    } elseif ($method == 'GET') { } elseif ($method == 'PUT') {
        return "PUT";
    } elseif ($method == 'DELETE') {
        return "DELETE";
    } elseif ($method == 'PATCH') {
        return "PATCH";
    } else {
        return "unknown";
    }
}

$production = true;


$servername = "localhost";
$username = "root";
$password = "";
$database = "mrm";

if ($production) {
    $servername = "localhost";
    $username = "gfnart_mrm_new";
    $password = "8765432187654321";
    $database = "gfnart_mrm";
}

$conn = new mysqli($servername, $username, $password, $database);


if (function_exists($_GET['f'])) {
    $_GET['f']();
}

function checkZoneId($conn, $zone_id)
{
    $checkZone = mysqli_query($conn, "SELECT * FROM zones where id ='$zone_id'");
    if (mysqli_num_rows($checkZone) > 0) {
        return true;
    } else {
        $errorData["data"] = array("status" => 0,   "message" => "Invalid zone_id");
        echo json_encode($errorData);

        die();
    }
}



function checkEmployeeId($conn, $emp_id)
{
    $checkEmployee = mysqli_query($conn, "SELECT * FROM employee where id =$emp_id AND account_status = 'BLOCKED' ");
    if (mysqli_num_rows($checkEmployee)) {
        $errorData["data"] = array("status" => 0,   "message" => "Employee Account is Blocked");
        echo json_encode($errorData);
        die();
    } else {
        $check = mysqli_query($conn, "SELECT * FROM employee WHERE id =$emp_id");
        if (mysqli_num_rows($check)) {
            return true;
        } else {
            $errorData["data"] = array("status" => 0,   "message" => "emp_id is invalid");
            echo json_encode($errorData);
            die();
        }
    }
}

function validateUserId($conn, $user_id)
{
    $userResult = mysqli_query($conn, "SELECT * from user where id=$user_id");
    //CHECK FOR BLOCKED CUSTOMER
    $check = mysqli_query($conn, "SELECT * FROM user WHERE id=$user_id AND account_status = 'BLOCKED' ");
    if (mysqli_num_rows($check) > 0) {
        $errorData["data"] = array("status" => 0,   "message" => "This customer Account is Blocked");
        echo json_encode($errorData);
        die();
    }
    //END oF BLOCKED

    if (mysqli_num_rows($userResult) != 0) {
        return true;
    } else if (mysqli_num_rows($userResult) == 0) {
        $errorData["data"] = array("status" => 0,   "message" => "The given customer ID is invalid");
        echo json_encode($errorData);
        die(mysqli_error($conn));
    }
}

function customerDetails()
{
    $conn = $GLOBALS['conn'];
    $user_id = (int) $_GET['user_id'];
    validateUserId($conn, $user_id);
    $sql = mysqli_query($conn, "select * from user where id= '$user_id'");
    $user = mysqli_fetch_assoc($sql);

    $user_address_id = (int) $user['user_address_id'];


    $checkaddress = mysqli_query($conn, "SELECT * FROM user_address WHERE user_id = $user_id AND id = $user_address_id");
    while ($rw = mysqli_fetch_assoc($checkaddress)) {
        $address = array(
            "user_address_id" => (int) $rw['id'],
            'location' => $rw['location'],
            'address' => $rw['address'],
            'latitude' => $rw['latitude'],
            'longitude' => $rw['longitude'],
            'zone_id' => (int) $rw['zone_id']
        );
    }

    $query1 = mysqli_query($conn, "SELECT SUM(amount) AS credits FROM `user_wallet` WHERE user_id=$user_id AND type = 'credit' ");
    $query2 = mysqli_query($conn, "SELECT SUM(amount) AS debits FROM `user_wallet` WHERE user_id=$user_id AND type = 'debit' ");
    $aa = mysqli_fetch_assoc($query1);
    $bb = mysqli_fetch_assoc($query2);
    $total_credits = (int) $aa['credits'];
    $total_debits = (int) $bb['debits'];
    $wallet_balance = $total_credits - $total_debits;



    $response = array(
        "data" => array(
            "status" => 1,
            "message" => "Customer details found",
            "customer" => array(
                "id" => (int) $user['id'],
                "account_status" => $user['account_status'],
                "wallet_balance" => (int) $wallet_balance,
                "reg_no" =>  $user['reg_no'],
                "selected_address" => $address,
                "name" => $user['name'],
                "email" =>  $user['email'],
                "country_code" =>  $user['country_code'],
                "cell" =>  $user['cell'],
                "gender" => $user['gender'],
                "joined_date" => $user['joined_date'],
                "is_referal_user" => $user['is_referal_user'],
                "is_referal_code" => $user['is_referal_code'],
                "is_refered_by" => $user['is_refered_by'],
                "is_register_complete" => (int) $user['is_register_complete']
            )
        )
    );

    echo json_encode($response);
}

function customerSendOTP()
{
    $conn = $GLOBALS['conn'];
    $input = json_decode(file_get_contents('php://input'), true);
    $cell = $input['cell'];
    $country_code = $input['code'];


    $mobileregex = "/^[6-9][0-9]{9}$/";
    if (!isset($input["cell"]) || empty($input["cell"]) || preg_match($mobileregex, $input["cell"]) == 0) {
        $errorData = null;
        $errorData["data"] = array("status" => 0,   "message" => "Phone number is invalid");
        echo json_encode($errorData);

        die();
    }
    $otp = 12345;
    $q1 = mysqli_query($conn, "DELETE FROM otp WHERE cell = '$cell' ");
    $q2 = mysqli_query($conn, "INSERT INTO otp(otp, country_code, cell)VALUES('$otp', '$country_code','$cell') ");
    if ($q2) {
        $response["data"] = array("status" => 1, "message" => "OTP sent successfully");
        echo json_encode($response);
        die();
    }
}


function customerResendOTP()
{
    $conn = $GLOBALS['conn'];
    $input = json_decode(file_get_contents('php://input'), true);
    $cell = $input['cell'];
    $country_code = $input['code'];

    $mobileregex = "/^[6-9][0-9]{9}$/";
    if (!isset($input["cell"]) || empty($input["cell"]) || preg_match($mobileregex, $input["cell"]) == 0) {
        $errorData = null;
        $errorData["data"] = array("status" => 0,   "message" => "Phone number is invalid");
        echo json_encode($errorData);

        die();
    }
    $otp = 12345;
    $q1 = mysqli_query($conn, "DELETE FROM otp WHERE cell = '$cell' ");
    $q2 = mysqli_query($conn, "INSERT INTO otp(otp,country_code, cell)VALUES('$otp','$country_code', '$cell') ");
    if ($q2) {
        $response["data"] = array("status" => 1, "message" => "OTP Resent successfully");
        echo json_encode($response);
        die();
    }
}


function customerVerifyOTP()
{
    $conn = $GLOBALS['conn'];
    $input = json_decode(file_get_contents('php://input'), true);
    $cell = $input['cell'];
    $otp = $input['otp'];

    $mobileregex = "/^[6-9][0-9]{9}$/";
    if (!isset($input["cell"]) || empty($input["cell"]) || preg_match($mobileregex, $input["cell"]) == 0) {
        $errorData = null;
        $errorData["data"] = array("status" => 0,   "message" => "Phone number is invalid");
        echo json_encode($errorData);

        die();
    }

    if (mysqli_num_rows(mysqli_query($conn, "SELECT * FROM otp WHERE cell='$cell' AND otp='$otp' LIMIT 1")) > 0) {
        $codeCheck = mysqli_query($conn, "SELECT country_code FROM otp WHERE cell='$cell' AND otp='$otp' LIMIT 1");
        $rw = mysqli_fetch_assoc($codeCheck);
        $country_code = $rw['country_code'];
        $result = mysqli_query($conn, "SELECT * FROM user WHERE cell='$cell' AND country_code = '$country_code' LIMIT 1");
        if (mysqli_num_rows($result) > 0) {
            //EXISTING CUSTOMER
            $fcm_id = $input['fcm_id'];
            $customer_fcmID = mysqli_query($conn, "UPDATE user SET fcm_id = '$fcm_id' WHERE cell='$cell'");
            //CHECK FOR BLOCKED CUSTOMER
            $check = mysqli_query($conn, "SELECT * FROM user WHERE cell='$cell' AND account_status = 'BLOCKED' ");
            if (mysqli_num_rows($check) > 0) {
                $errorData["data"] = array("status" => 0,   "message" => "This customer Account is Blocked");
                echo json_encode($errorData);
                die();
            }
            //END oF BLOCKED


            $row = mysqli_fetch_assoc($result);
            $address = NULL;
            //GET address details
            $user_address_id = (int) $row['user_address_id'];
            $user_id = (int) $row['id'];
            $checkaddress = mysqli_query($conn, "SELECT * FROM user_address WHERE user_id = $user_id AND id = $user_address_id");
            while ($rw = mysqli_fetch_assoc($checkaddress)) {
                $address = array(
                    "user_address_id" => (int) $rw['id'],
                    'location' => $rw['location'],
                    'address' => $rw['address'],
                    'latitude' => $rw['latitude'],
                    'longitude' => $rw['longitude'],
                    'zone_id' => (int) $rw['zone_id']
                );
            }


            //address details closed

            $response = array(
                "data" => array(
                    "status" => 1,
                    "message" => "OTP verified, Login successful",
                    "customer" => array(
                        "id" => (int) $row['id'],
                        "reg_no" =>  $row['reg_no'],
                        "selected_address" => $address,
                        "name" => $row['name'],
                        "email" =>  $row['email'],
                        "country_code" =>  $row['country_code'],
                        "cell" =>  $row['cell'],
                        "gender" => $row['gender'],
                        "joined_date" => $row['joined_date'],
                        "is_referal_user" => $row['is_referal_user'],
                        "is_referal_code" => $row['is_referal_code'],
                        "is_refered_by" => $row['is_refered_by'],
                        "is_register_complete" => (int) $row['is_register_complete']
                    )
                )
            );

            echo json_encode($response);

            $conn->query("DELETE FROM otp WHERE otp = $otp AND cell='$cell' AND country_code = '$country_code' ");
            die(mysqli_error($conn));
        } else {
            //New CUSTOMER
            $fcm_id = $input['fcm_id'];
            $referral_code = bin2hex(openssl_random_pseudo_bytes(5));

            if ($conn->query("INSERT INTO `user` (fcm_id, country_code, `cell`, `referral_code`)VALUES ('$fcm_id','$country_code','$cell', '$referral_code')")) {
                $user_id = $conn->insert_id;
                $result = mysqli_query($conn, "SELECT * FROM user WHERE id = '$user_id' LIMIT 1");
                $row = mysqli_fetch_assoc($result);
                $response = array(
                    "data" => array(
                        "status" => 2,
                        "message" => "OTP verified, Login successful",
                        "customer" => array(
                            "id" => (int) $row['id'],
                            "reg_no" =>  $row['reg_no'],
                            "selected_address" => array(),
                            "name" => $row['name'],
                            "email" =>  $row['email'],
                            "country_code" =>  $row['country_code'],
                            "cell" =>  $row['cell'],
                            "gender" => $row['gender'],
                            "joined_date" => $row['joined_date'],
                            "is_referal_user" => $row['is_referal_user'],
                            "is_referal_code" => $row['is_referal_code'],
                            "is_refered_by" => $row['is_refered_by'],
                            "is_register_complete" => (int) $row['is_register_complete']
                        )
                    )
                );

                echo json_encode($response);
                $conn->query("DELETE FROM otp WHERE otp = $otp AND cell='$cell' AND country_code = '$country_code' ");
                die(mysqli_error($conn));
            } else {
                die(mysqli_error($conn));
            }
        }
    } else {
        $response["data"] = array("status" => 0, "code" => 400, "message" => "Invalid data");
        echo json_encode($response);

        die();
    }
}

function postUser()
{

    $inputJSON = file_get_contents('php://input');

    $input = json_decode($inputJSON, TRUE);

    $profile_update = (int) $input['profile_update'];


    $name = $input['name'];

    $email = $input['email'];

    // Validate email 

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response["data"] = array("status" => 0, "code" => 400, "message" => "Invalid email format");
        echo json_encode($response);
        die();
    }

    $gender = $input['gender'];

    $cell = $input['cell'];

    $userid = (int) $input['user_id'];

    if ($profile_update == 0) {

        $regcom = 1;

        $conn = $GLOBALS['conn'];


        //CHECK FOR BLOCKED CUSTOMER
        $check = mysqli_query($conn, "SELECT * FROM user WHERE id = $userid AND account_status = 'BLOCKED' ");
        if (mysqli_num_rows($check) > 0) {
            $errorData["data"] = array("status" => 0,   "message" => "This customer Account is Blocked");
            echo json_encode($errorData);
            die();
        }
        //END oF BLOCKED


        $checkcell = mysqli_query($conn, "SELECT * FROM user WHERE id = $userid ");
        $current = mysqli_fetch_assoc($checkcell);
        $is_register_complete = (int) $current['is_register_complete'];

        if ($is_register_complete > 1) {
            $errorData["data"] = array("status" => 0,   "message" => "mobile number already registered");
            echo json_encode($errorData);

            die();
        }

        if (isset($input['referal_code'])) {
            $referalcode = $input['referal_code'];
            $query = "UPDATE user SET name='$name',email='$email', cell = '$cell', gender = '$gender', is_referal_code='$referalcode',is_register_complete='$regcom' WHERE id='$userid'";
        } else if (!isset($input['referal_code'])) {
            $query = "UPDATE user SET name='$name',email='$email', cell = '$cell', gender = '$gender',is_register_complete='$regcom' WHERE id='$userid'";
        }



        if (mysqli_query($conn, $query)) {

            $userquery = "select * from user where id= '$userid'";

            $sql = mysqli_query($conn, $userquery);

            $user = mysqli_fetch_assoc($sql);

            $address = array();

            $response = array(
                "data" => array(
                    "status" => 1,
                    "message" => "Record updated",
                    "customer" => array(
                        "id" => (int) $user['id'],
                        "reg_no" =>  $user['reg_no'],
                        "selected_address" => $address,
                        "name" => $user['name'],
                        "email" =>  $user['email'],
                        "country_code" =>  $user['country_code'],
                        "cell" =>  $user['cell'],
                        "gender" => $user['gender'],
                        "joined_date" => $user['joined_date'],
                        "is_referal_user" => $user['is_referal_user'],
                        "is_referal_code" => $user['is_referal_code'],
                        "is_refered_by" => $user['is_refered_by'],
                        "is_register_complete" => (int) $user['is_register_complete']
                    )
                )
            );

            echo json_encode($response);
        } else {

            echo json_encode(array(
                'status' => 0,
                'message' => 'Something went to wrong'
            ));
        }
    } else if ($profile_update == 1) {

        $conn = $GLOBALS['conn'];
        //CHECK FOR BLOCKED CUSTOMER
        $check = mysqli_query($conn, "SELECT * FROM user WHERE id = $userid AND account_status = 'BLOCKED' ");
        if (mysqli_num_rows($check) > 0) {
            $errorData["data"] = array("status" => 0,   "message" => "This customer Account is Blocked");
            echo json_encode($errorData);
            die();
        }
        //END oF BLOCKED


        $query = "UPDATE user SET name='$name',email='$email', cell = '$cell', gender = '$gender', is_referal_code='$referalcode' WHERE id='$userid'";

        if (mysqli_query($conn, $query)) {

            $userquery = "select * from user where id= $userid";

            $sql = mysqli_query($conn, $userquery);

            $user = mysqli_fetch_assoc($sql);

            $user_address_id = (int) $user['user_address_id'];
            $address = NULL;

            $checkaddress = mysqli_query($conn, "SELECT * FROM user_address WHERE user_id = $userid AND id = $user_address_id");
            while ($rw = mysqli_fetch_assoc($checkaddress)) {
                $address = array(
                    "user_address_id" => (int) $rw['id'],
                    'location' => $rw['location'],
                    'address' => $rw['address'],
                    'latitude' => $rw['latitude'],
                    'longitude' => $rw['longitude'],
                    'zone_id' => (int) $rw['zone_id']
                );
            }

            $response = array(
                "data" => array(
                    "status" => 1,
                    "message" => "Customer details found",
                    "customer" => array(
                        "id" => (int) $user['id'],
                        "reg_no" =>  $user['reg_no'],
                        "selected_address" => $address,
                        "name" => $user['name'],
                        "email" =>  $user['email'],
                        "country_code" =>  $user['country_code'],
                        "cell" =>  $user['cell'],
                        "gender" => $user['gender'],
                        "joined_date" => $user['joined_date'],
                        "is_referal_user" => $user['is_referal_user'],
                        "is_referal_code" => $user['is_referal_code'],
                        "is_refered_by" => $user['is_refered_by'],
                        "is_register_complete" => (int) $user['is_register_complete']
                    )
                )
            );

            echo json_encode($response);
        } else {

            echo json_encode(array(
                'status' => 0,
                'message' => 'Something went to wrong'
            ));
        }
    }
}



function addCustomerAddress()
{
    $input = json_decode(file_get_contents('php://input'), true);
    $userid = (int) $input['user_id'];
    $location = $input['location'];
    $address = $input['address'];
    $latitude = $input['latitude'];
    $longitude = $input['longitude'];
    $zone_id = 0;
    $zone_id = (int) $input['zone_id'];

    $conn = $GLOBALS['conn'];

    //CHECK FOR BLOCKED CUSTOMER
    $check = mysqli_query($conn, "SELECT * FROM user WHERE id = $userid AND account_status = 'BLOCKED' ");
    if (mysqli_num_rows($check) > 0) {
        $errorData["data"] = array("status" => 0,   "message" => "This customer Account is Blocked");
        echo json_encode($errorData);
        die();
    }
    //END oF BLOCKED



    validateUserId($conn, $userid);

    $check = mysqli_query($conn, "SELECT * FROM user_address
     WHERE user_id = $userid AND location = '$location' AND address ='$address' AND  latitude = '$latitude' AND longitude = '$longitude' ");

    if (mysqli_num_rows($check) > 0) {
        $errorData["data"] = array("status" => 0,   "message" => "Address already found");
        echo json_encode($errorData);

        die();
    }
    $query = "INSERT INTO user_address(user_id, location, address, latitude, longitude, zone_id) 
             VALUES ($userid,'$location','$address','$latitude','$longitude', $zone_id)";


    if (mysqli_query($conn, $query)) {
        $user_address_id = $conn->insert_id;

        $zz = mysqli_query($conn, "SELECT * FROM user_address WHERE user_id = $userid");
        if (mysqli_num_rows($zz) == 1) {
            $updateaddress = mysqli_query($conn, "UPDATE user SET user_address_id = $user_address_id WHERE id = $userid ");
        }


        $queryup = "UPDATE user SET is_register_complete= 2 WHERE id='$userid'";
        mysqli_query($conn, $queryup);


        $userquery = "select * from user where id= '$userid'";

        $sql = mysqli_query($conn, $userquery);

        $user = mysqli_fetch_object($sql);

        $data = [

            'status' => 1,
            'data' => $user,
            'message' => 'Record Updated'
        ];

        echo json_encode($data);
        die(mysqli_error($conn));
    } else {
        $response['error'] = array(
            'status' => 0,
            'message' => 'Failure! Something went wrong in server'
        );
        echo json_encode($response);
        die(mysqli_error($conn));
    }
}

function viewCustomerAddress()
{
    $userid = (int) $_GET['user_id'];
    $conn = $GLOBALS['conn'];


    //CHECK FOR BLOCKED CUSTOMER
    $check = mysqli_query($conn, "SELECT * FROM user WHERE id=$userid AND account_status = 'BLOCKED' ");
    if (mysqli_num_rows($check) > 0) {
        $errorData["data"] = array("status" => 0,   "message" => "This customer Account is Blocked");
        echo json_encode($errorData);
        die();
    }
    //END oF BLOCKED


    validateUserId($conn, $userid);


    $userquery = "select * from user where id= '$userid'";
    $sql = mysqli_query($conn, $userquery);
    $user = mysqli_fetch_assoc($sql);
    $user_address_id = (int) $user['user_address_id'];
    $checkaddress = mysqli_query($conn, "SELECT * FROM user_address WHERE user_id = $userid");
    if (mysqli_num_rows($checkaddress) > 0) {
        while ($rw = mysqli_fetch_assoc($checkaddress)) {

            if ((int) $rw['id'] == $user_address_id) {
                $address[] = array(
                    "user_address_id" => (int) $rw['id'],
                    "selected" => "YES",
                    'location' => $rw['location'],
                    'address' => $rw['address'],
                    'latitude' => $rw['latitude'],
                    'longitude' => $rw['longitude'],
                    'zone_id' => (int) $rw['zone_id']
                );
            } else {
                $address[] = array(
                    "user_address_id" => (int) $rw['id'],
                    "selected" => "NO",
                    'location' => $rw['location'],
                    'address' => $rw['address'],
                    'latitude' => $rw['latitude'],
                    'longitude' => $rw['longitude'],
                    'zone_id' => (int) $rw['zone_id']
                );
            }
        }
        $data = array("data" => array("status" => 1, "message" => "Customer addresses found", "address" => $address));
        echo json_encode($data);
        die(mysqli_error($conn));
    } else {

        $errorData["data"] = array("status" => 0,   "message" => "No address found");
        echo json_encode($errorData);

        die();
    }
}



function slots()
{
    if (!isset($_GET['zone_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No zone_id is supplied");
        echo json_encode($errorData);

        die();
    }

    $zone_id = (int) $_GET['zone_id'];

    $conn = $GLOBALS['conn'];

    checkZoneId($conn, $zone_id);


    date_default_timezone_set('Asia/Calcutta');
    $today_date =  date('Y-m-d');

    $datetime = new DateTime(date('Y-m-d'));
    $datetime->modify('+1 day');
    $tomorrow_date =  $datetime->format('Y-m-d');

    $datetime = new DateTime(date('Y-m-d'));
    $datetime->modify('+2 day');
    $dayaftertomorrow_date =  $datetime->format('Y-m-d');


    // POPULATE TODAY////////////////////////////////////////////////////////////////////////////////////////////////////////////
    $slots_today = [];

    $res = mysqli_query($conn, "SELECT distinct(slot_id), 
                (SELECT count(emp_id) as count from available_slot a WHERE a.slot_id = b.slot_id AND  date='$today_date' AND available='YES' AND zone_id=$zone_id) as count,
                s.name as name
                from available_slot b
        	INNER JOIN slot s ON s.id = b.slot_id");

    if (mysqli_num_rows($res)) {
        while ($current_row = mysqli_fetch_assoc($res)) {

            $slot_id = (int) $current_row['slot_id'];
            //check already booked bookings
            $bookedalreadycheck = mysqli_query($conn, "SELECT b.slot_id,COUNT(b.emp_id) as count FROM booking b INNER JOIN slot s ON b.slot_id = s.id
                                                            	WHERE b.slot_id=$slot_id
                                                                AND b.date='$date'
                                                                AND b.status IN ('PLACED','ACCEPTED','RESCHEDULED')
                                                                AND b.payment = 'PENDING'");
            $bookedCount = 0;
            if (mysqli_num_rows($bookedalreadycheck) > 0) {
                $thisrow = mysqli_fetch_assoc($bookedalreadycheck);
                $bookedCount = (int) $thisrow['count'];
            }

            $resultantCount = (int) $current_row['count'] - $bookedCount;
            //already booked block ends here

            if ($resultantCount < 0) {
                $resultantCount = 0;
            }

            if (($current_row['name'] == '07:00 AM to 09:00 AM') || ($current_row['name'] == '09:00 AM to 11:00 AM')) {
                $slots_today[] = array(
                    "slot_id" => (int) $current_row['slot_id'],
                    "count" => $resultantCount,
                    "available" => $resultantCount > 0 ? "YES" : "NO",
                    "name" => $current_row['name'],
                    "period" => "Morning"
                );
            } else if (($current_row['name'] == '11:00 AM to 01:00 PM') || ($current_row['name'] == '01:00 PM to 03:00 PM')) {
                $slots_today[] = array(
                    "slot_id" => (int) $current_row['slot_id'],
                    "count" => $resultantCount,
                    "available" => $resultantCount > 0 ? "YES" : "NO",
                    "name" => $current_row['name'],
                    "period" => "Afternoon"
                );
            } else if (($current_row['name'] == '03:00 PM to 05:00 PM') || ($current_row['name'] == '05:00 PM to 07:00 PM') || ($current_row['name'] == '07:00 PM to 09:00 PM')) {
                $slots_today[] = array(
                    "slot_id" => (int) $current_row['slot_id'],
                    "count" => $resultantCount,
                    "available" => $resultantCount > 0 ? "YES" : "NO",
                    "name" => $current_row['name'],
                    "period" => "Evening"
                );
            }
        }
        $today_overall = array("date" => $today_date, "slots" => $slots_today);
    } else {
        $errorData["data"] = array("status" => 0,   "message" => "Invalid zone_id");
        echo json_encode($errorData);

        die(mysqli_error($conn));
    }

    // TODAY END////////////////////////////////////////////////////////////////////////////////////////////////////////////

    //POPULATE TOMORROW////////////////////////////////////////////////////////////////////////////////////////////////////////////
    $slots_tomorrow = [];

    $res = mysqli_query($conn, "SELECT distinct(slot_id), 
                (SELECT count(emp_id) as count from available_slot a WHERE a.slot_id = b.slot_id AND  date='$tomorrow_date' AND available='YES' AND zone_id=$zone_id) as count,
                s.name as name
                from available_slot b
        	INNER JOIN slot s ON s.id = b.slot_id");

    if (mysqli_num_rows($res)) {
        while ($current_row = mysqli_fetch_assoc($res)) {

            $slot_id = (int) $current_row['slot_id'];
            //check already booked bookings
            $bookedalreadycheck = mysqli_query($conn, "SELECT b.slot_id,COUNT(b.emp_id) as count FROM booking b INNER JOIN slot s ON b.slot_id = s.id
                                                            	WHERE b.slot_id=$slot_id
                                                                AND b.date='$tomorrow_date'
                                                                AND b.status IN ('PLACED','ACCEPTED','RESCHEDULED')
                                                                AND b.payment = 'PENDING'");
            $bookedCount = 0;
            if (mysqli_num_rows($bookedalreadycheck) > 0) {
                $thisrow = mysqli_fetch_assoc($bookedalreadycheck);
                $bookedCount = (int) $thisrow['count'];
            }

            $resultantCount = (int) $current_row['count'] - $bookedCount;
            //already booked block ends here

            if ($resultantCount < 0) {
                $resultantCount = 0;
            }

            if (($current_row['name'] == '07:00 AM to 09:00 AM') || ($current_row['name'] == '09:00 AM to 11:00 AM')) {
                $slots_tomorrow[] = array(
                    "slot_id" => (int) $current_row['slot_id'],
                    "count" => $resultantCount,
                    "available" => $resultantCount > 0 ? "YES" : "NO",
                    "name" => $current_row['name'],
                    "period" => "Morning"
                );
            } else if (($current_row['name'] == '11:00 AM to 01:00 PM') || ($current_row['name'] == '01:00 PM to 03:00 PM')) {
                $slots_tomorrow[] = array(
                    "slot_id" => (int) $current_row['slot_id'],
                    "count" => $resultantCount,
                    "available" => $resultantCount > 0 ? "YES" : "NO",
                    "name" => $current_row['name'],
                    "period" => "Afternoon"
                );
            } else if (($current_row['name'] == '03:00 PM to 05:00 PM') || ($current_row['name'] == '05:00 PM to 07:00 PM') || ($current_row['name'] == '07:00 PM to 09:00 PM')) {
                $slots_tomorrow[] = array(
                    "slot_id" => (int) $current_row['slot_id'],
                    "count" => $resultantCount,
                    "available" => $resultantCount > 0 ? "YES" : "NO",
                    "name" => $current_row['name'],
                    "period" => "Evening"
                );
            }
        }
        $tomorrow_overall = array("date" => $tomorrow_date, "slots" => $slots_tomorrow);
    } else {
        $errorData["data"] = array("status" => 0,   "message" => "Invalid zone_id");
        echo json_encode($errorData);

        die(mysqli_error($conn));
    }

    //TOMORROW END////////////////////////////////////////////////////////////////////////////////////////////////////////////

    //POPULATE DAY AFTER TOMORROW////////////////////////////////////////////////////////////////////////////////////////////////////////////
    $slots_dayaftertomorrow = [];

    $res = mysqli_query($conn, "SELECT distinct(slot_id), 
                (SELECT count(emp_id) as count from available_slot a WHERE a.slot_id = b.slot_id AND  date='$dayaftertomorrow_date' AND available='YES' AND zone_id=$zone_id) as count,
                s.name as name
                from available_slot b
        	INNER JOIN slot s ON s.id = b.slot_id");

    if (mysqli_num_rows($res)) {
        while ($current_row = mysqli_fetch_assoc($res)) {

            $slot_id = (int) $current_row['slot_id'];
            //check already booked bookings
            $bookedalreadycheck = mysqli_query($conn, "SELECT b.slot_id,COUNT(b.emp_id) as count FROM booking b INNER JOIN slot s ON b.slot_id = s.id
                                                            	WHERE b.slot_id=$slot_id
                                                                AND b.date='$dayaftertomorrow_date'
                                                                AND b.status IN ('PLACED','ACCEPTED','RESCHEDULED')
                                                                AND b.payment = 'PENDING'");
            $bookedCount = 0;
            if (mysqli_num_rows($bookedalreadycheck) > 0) {
                $thisrow = mysqli_fetch_assoc($bookedalreadycheck);
                $bookedCount = (int) $thisrow['count'];
            }

            $resultantCount = (int) $current_row['count'] - $bookedCount;
            //already booked block ends here

            if ($resultantCount < 0) {
                $resultantCount = 0;
            }

            if (($current_row['name'] == '07:00 AM to 09:00 AM') || ($current_row['name'] == '09:00 AM to 11:00 AM')) {
                $slots_dayaftertomorrow[] = array(
                    "slot_id" => (int) $current_row['slot_id'],
                    "count" => $resultantCount,
                    "available" => $resultantCount > 0 ? "YES" : "NO",
                    "name" => $current_row['name'],
                    "period" => "Morning"
                );
            } else if (($current_row['name'] == '11:00 AM to 01:00 PM') || ($current_row['name'] == '01:00 PM to 03:00 PM')) {
                $slots_dayaftertomorrow[] = array(
                    "slot_id" => (int) $current_row['slot_id'],
                    "count" => $resultantCount,
                    "available" => $resultantCount > 0 ? "YES" : "NO",
                    "name" => $current_row['name'],
                    "period" => "Afternoon"
                );
            } else if (($current_row['name'] == '03:00 PM to 05:00 PM') || ($current_row['name'] == '05:00 PM to 07:00 PM') || ($current_row['name'] == '07:00 PM to 09:00 PM')) {
                $slots_dayaftertomorrow[] = array(
                    "slot_id" => (int) $current_row['slot_id'],
                    "count" => $resultantCount,
                    "available" => $resultantCount > 0 ? "YES" : "NO",
                    "name" => $current_row['name'],
                    "period" => "Evening"
                );
            }
        }
        $dayaftertomorrow_overall = array("date" => $dayaftertomorrow_date, "slots" => $slots_dayaftertomorrow);
    } else {
        $errorData["data"] = array("status" => 0,   "message" => "Invalid zone_id");
        echo json_encode($errorData);

        die(mysqli_error($conn));
    }
    //DAY AFTER TOMORROW END////////////////////////////////////////////////////////////////////////////////////////////////////////////
    $response["data"] = array("status" => 1, "message" => "Slots for current 3 days", "today" => $today_overall, "tomorrow" => $tomorrow_overall, "day_after_tomorrow" => $dayaftertomorrow_overall);
    echo json_encode($response);
    die(mysqli_error($conn));
}



function customerConfirmSlot()
{
    $input = json_decode(file_get_contents('php://input'), true);
    $conn = $GLOBALS['conn'];

    if (!isset($input['date'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No date is supplied");
        echo json_encode($errorData);

        die();
    }

    if (!isset($input['user_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No user_id is supplied");
        echo json_encode($errorData);

        die();
    }

    if (!isset($input['slot_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No slot_id is supplied");
        echo json_encode($errorData);

        die();
    }
    $user_id = (int) $input['user_id'];

    //CHECK FOR BLOCKED CUSTOMER
    $checkk = mysqli_query($conn, "SELECT * FROM user WHERE id=$user_id AND account_status = 'BLOCKED' ");
    if (mysqli_num_rows($checkk) > 0) {
        $errorData["data"] = array("status" => 0,   "message" => "This customer Account is Blocked");
        echo json_encode($errorData);
        die();
    }
    //END oF BLOCKED


    validateUserId($conn, $user_id);
    $slot_id = (int) $input['slot_id'];
    $date = $input['date'];


    if (strtotime($date) < strtotime(date('Y-m-d'))) {
        $errorData["data"] = array("status" => 0,   "message" => "Past date is supplied");
        echo json_encode($errorData);

        die(mysqli_error($conn));
    }


    $q1 = mysqli_query($conn, "SELECT * from cart WHERE user_id = $user_id");
    if (mysqli_num_rows($q1)) {
        $rrr = mysqli_fetch_assoc($q1);
        $user_address_id = (int) $rrr['user_address_id'];

        $getzone = mysqli_query($conn, "SELECT zone_id from user_address WHERE user_id = $user_id AND id =$user_address_id LIMIT 1");
        $thisrow = mysqli_fetch_assoc($getzone);
        $zone_id = (int) $thisrow['zone_id'];

        checkZoneId($conn, $zone_id);

        $res = mysqli_query($conn, "SELECT slot_id, 
                (SELECT count(emp_id) as count from available_slot a WHERE a.slot_id = b.slot_id AND  date='$date' AND available='YES' AND zone_id=$zone_id) as count,
                s.name as name
                from available_slot b
            INNER JOIN slot s ON s.id = b.slot_id
            WHERE b.slot_id = $slot_id");

        if (mysqli_num_rows($res)) {
            $current_row = mysqli_fetch_assoc($res);


            //check already booked
            $bookedalreadycheck = mysqli_query($conn, "SELECT b.slot_id,COUNT(b.emp_id) as count FROM booking b INNER JOIN slot s ON b.slot_id = s.id
                                                    	WHERE b.slot_id=$slot_id
                                                        AND b.date='$date'
                                                        AND b.status IN ('PLACED','ACCEPTED','RESCHEDULED')
                                                        AND b.payment = 'PENDING'");
            $bookedCount = 0;
            if (mysqli_num_rows($bookedalreadycheck) > 0) {
                $thisrow = mysqli_fetch_assoc($bookedalreadycheck);
                $bookedCount = (int) $thisrow['count'];
            }

            $resultantCount = (int) $current_row['count'] - $bookedCount;
            //already booked block ends here


            if ($resultantCount > 0) {


                //count available now, so confirm this slot
                $q2 = mysqli_query($conn, "UPDATE cart SET slot_id =$slot_id, date = '$date' WHERE user_id= $user_id");

                $q11 = mysqli_query($conn, "SELECT * from cart WHERE user_id = $user_id");
                $rrr = mysqli_fetch_assoc($q11);
                $cart_id = (int) $rrr['id'];
                $cartitemcheck = mysqli_query($conn, "SELECT * from cart_item WHERE cart_id=$cart_id ");
                $cartitems = [];
                while ($rrow = mysqli_fetch_assoc($cartitemcheck)) {
                    $productId = (int) $rrow['product_id'];
                    $checkname = mysqli_query($conn, "SELECT product_title, product_description from products WHERE id = $productId");
                    $r = mysqli_fetch_assoc($checkname);
                    $product_title = $r['product_title'];
                    $product_description = $r['product_description'];

                    $checkproductname = mysqli_query($conn, "SELECT mc.name FROM main_categories mc INNER JOIN sub_categories sc ON sc.main_category_id=mc.id
                                                                INNER JOIN products p on p.category_id = sc.id AND p.id=$productId ");
                    $rw = mysqli_fetch_assoc($checkproductname);
                    $mainCategoryName = $rw['name'];

                    $cartitems[] = array(
                        "cartItemId" => (int) $rrow['id'],
                        "productId" => (int) $rrow['product_id'],
                        "productTitle" => $product_title,
                        "productDescription" => $product_description,
                        "mainCategoryName" => $mainCategoryName,
                        "count" => (int) $rrow['count'],
                        "price" => (int) $rrow['price']
                    );
                }

                $cartcheck = mysqli_query($conn, "SELECT * from cart WHERE id=$cart_id ");
                $cartrow = mysqli_fetch_assoc($cartcheck);
                $response["data"] = array(
                    "status" => 1,
                    "message" => "Slot confirmed and added to cart",
                    "user_id" => $user_id,
                    "cartId" => (int) $cartrow['id'],
                    "estimate" => (int) $cartrow['estimate'],
                    "user_address_id" => (int) $cartrow['user_address_id'],
                    "date" => $cartrow['date'],
                    "slot_id" => (int) $cartrow['slot_id'],
                    "cartItems" => $cartitems
                );
                echo json_encode($response);
                die(mysqli_error($conn));
            }
            //
            else {
                //count = 0, so unavailable at the moment
                $errorData["data"] = array("status" => 0,   "message" => "This slot is not available for user's address zone and given date");
                echo json_encode($errorData);

                die(mysqli_error($conn));
            }
        }
        //
        else {
            $errorData["data"] = array("status" => 0,   "message" => "This slot is not available");
            echo json_encode($errorData);

            die(mysqli_error($conn));
        }
    } else {

        ////////////////////////////////////////////////////////THIS IS FOR 4 or 15 SERVICES (TYPE 2A or 2B)//////////////////////////////////
        $getUserAddressId = mysqli_query($conn, "SELECT user_address_id from user WHERE id = $user_id LIMIT 1");
        $thisroww = mysqli_fetch_assoc($getUserAddressId);
        $user_address_id = (int) $thisroww['user_address_id'];

        $getzone = mysqli_query($conn, "SELECT zone_id from user_address WHERE user_id = $user_id AND id =$user_address_id LIMIT 1");
        $thisrow = mysqli_fetch_assoc($getzone);
        $zone_id = (int) $thisrow['zone_id'];

        checkZoneId($conn, $zone_id);

        $res = mysqli_query($conn, "SELECT slot_id, 
                            (SELECT count(emp_id) as count from available_slot a WHERE a.slot_id = b.slot_id AND  date='$date' AND available='YES' AND zone_id=$zone_id) as count,
                            s.name as name
                            from available_slot b
                        INNER JOIN slot s ON s.id = b.slot_id
                        WHERE b.slot_id = $slot_id");

        if (mysqli_num_rows($res)) {
            $current_row = mysqli_fetch_assoc($res);


            //check already booked
            $bookedalreadycheck = mysqli_query($conn, "SELECT b.slot_id,COUNT(b.emp_id) as count FROM booking b INNER JOIN slot s ON b.slot_id = s.id
                                                                	WHERE b.slot_id=$slot_id
                                                                    AND b.date='$date'
                                                                    AND b.status IN ('PLACED','ACCEPTED','RESCHEDULED')
                                                                    AND b.payment = 'PENDING'");
            $bookedCount = 0;
            if (mysqli_num_rows($bookedalreadycheck) > 0) {
                $thisrow = mysqli_fetch_assoc($bookedalreadycheck);
                $bookedCount = (int) $thisrow['count'];
            }

            $resultantCount = (int) $current_row['count'] - $bookedCount;
            //already booked block ends here


            if ($resultantCount > 0) {


                //count available now, so confirm this slot

                $response["data"] = array(
                    "status" => 1,
                    "message" => "This slot is available",
                    "user_id" => $user_id,
                    "user_address_id" => $user_address_id,
                    "date" => $date,
                    "slot_id" => $slot_id
                );
                echo json_encode($response);
                die(mysqli_error($conn));
            }
            //
            else {
                //count = 0, so unavailable at the moment
                $errorData["data"] = array("status" => 0,   "message" => "This slot is not available for user's address zone and given date");
                echo json_encode($errorData);

                die(mysqli_error($conn));
            }
        }
        //
        else {
            $errorData["data"] = array("status" => 0,   "message" => "This slot is not available");
            echo json_encode($errorData);

            die(mysqli_error($conn));
        }
    }
}





function getBanners()
{

    $conn = $GLOBALS['conn'];

    $banners = "select * from banners where is_display= 'YES'";

    $sql = mysqli_query($conn, $banners);

    $resultset = array();

    while ($row = mysqli_fetch_assoc($sql)) {
        $resultset[] = $row;
    }


    $datainfo = [

        'status' => 1,
        'data' => $resultset,
        'message' => 'Banners'
    ];

    echo json_encode($datainfo);
}

function getHomepage()
{

    $conn = $GLOBALS['conn'];

    $banners = "select * from banners where is_display= 'YES'";

    $sql = mysqli_query($conn, $banners);



    $resultset = array();

    while ($row = mysqli_fetch_assoc($sql)) {
        $resultset[] = $row;
    }

    $cat_level1 = "select * from home_categories where is_display= 'YES'";
    $sql1 = mysqli_query($conn, $cat_level1);
    $homecat = array();
    while ($row1 = mysqli_fetch_assoc($sql1)) {
        $homecat[] = $row1;
    }

    $main_categories = "select * from main_categories where is_display= 'YES'";
    $main_categories_sql = mysqli_query($conn, $main_categories);
    $main_cat = array();
    while ($main_categories_row = mysqli_fetch_assoc($main_categories_sql)) {
        $main_cat[] = $main_categories_row;
    }

    $datainfo = [
        'status' => 1,
        'banners' => $resultset,
        'homelevelcategory' => $homecat,
        'Maincategory' => $main_cat,
        'message' => 'Banner'
    ];
    echo json_encode($datainfo);
}



function getHomeCategories()
{
    $input = json_decode(file_get_contents('php://input'), true);
    $conn = $GLOBALS['conn'];
    //No category ID passed
    if (!isset($_GET['category_id']) && empty($_GET['category_id'])) {
        $results = $conn->query("SELECT h.id as home_id, h.name as home_name, h.image as home_image, m.id as main_id, home_category_id, m.name as main_name, m.image as main_image, m.icon as main_icon FROM main_categories m
        INNER JOIN home_categories h ON m.home_category_id = h.id");

        $re = mysqli_num_rows($results);

        if ($re > 0) {
            $all_rows = [];
            $type_id_name_map = [];
            $type_id_image_map = [];
            $distinct_type_ids = [];
            $home_cat = [];

            while ($row = mysqli_fetch_assoc($results)) {
                $all_rows[] = $row;

                $type_id_name_map[$row["home_category_id"]] = $row["home_name"];
                $type_id_image_map[$row["home_category_id"]] = $row["home_image"];

                $distinct_type_ids[] = $row["home_category_id"];
            }
            $response = null;
            $distinct_type_ids = array_values(array_unique($distinct_type_ids));

            foreach ($distinct_type_ids as $type_id) {
                $user_array = [];
                foreach ($all_rows as $current_row) {
                    if ($type_id === $current_row["home_category_id"]) {
                        $user_array[] = array(
                            "mainCategoryId" => (int) $current_row['main_id'],
                            "mainName" => $current_row['main_name'],
                            "mainImage" => $current_row['main_image']
                        );
                    }
                }
                $home_cat[] = array(
                    "homeCategoryId" => (int) $type_id,
                    "homeName" => $type_id_name_map[$type_id],
                    "homeImage" => $type_id_image_map[$type_id],
                    "mainCategories" => $user_array
                );
            }


            $response["data"] = array("status" => 1, "message" => "All categories in Home Page", "homeCategories" => $home_cat);
            echo json_encode($response);
            die(mysqli_error($conn));
        } else {
            $errorData["data"] = array("status" => 0,   "message" => "There is no home categories or service categories");
            echo json_encode($errorData);

            die(mysqli_error($conn));
        }
    }
    //category ID passed//////////////////////
    else if (isset($_GET['category_id'])) {
        $input = json_decode(file_get_contents('php://input'), true);
        $category_id = (int) $_GET['category_id'];
        $check = mysqli_query($conn, "select mc.id, mc.icon, mc.name, hc.name as hc_name from main_categories mc
                                        INNER JOIN home_categories hc
                                        ON mc.home_category_id = hc.id
                                        where mc.home_category_id= $category_id  ");
        if (mysqli_num_rows($check) < 1) {
            $errorData["data"] = array("status" => 0,   "message" => "No categories found");
            echo json_encode($errorData);

            die(mysqli_error($conn));
        }

        while ($row = mysqli_fetch_assoc($check)) {
            $homeCategoryName = $row['hc_name'];
            $mainCategories[] = array(
                "mainCategoryId" => (int) $row['id'],
                "icon" => $row['icon'],
                "title" => $row['name']
            );
        }
        $response['data'] = array("status" => 1, "message" => "All main categories under home category ID : $category_id", "homeCategoryId" => $category_id, "homeCategoryName" => $homeCategoryName, "mainCategories" => $mainCategories);
        echo json_encode($response);
    }
}

function homeSelectiveMainCategories()
{
    $conn = $GLOBALS['conn'];

    $check = mysqli_query($conn, "select * from main_categories WHERE show_in_home='YES' ");
    if (mysqli_num_rows($check) < 1) {
        $errorData["data"] = array("status" => 0,   "message" => "No categories found");
        echo json_encode($errorData);

        die(mysqli_error($conn));
    }

    while ($row = mysqli_fetch_assoc($check)) {
        $mainCategories[] = array(
            "mainCategoryId" => (int) $row['id'],
            "icon" => $row['icon'],
            "image" => $row['image'],
            "title" => $row['name']
        );
    }
    $response['data'] = array("status" => 1, "message" => "main categories selected by admin to show in home", "mainCategories" => $mainCategories);
    echo json_encode($response);
    die(mysqli_error($conn));
}

function getMainCategories()
{
    $input = json_decode(file_get_contents('php://input'), true);
    $conn = $GLOBALS['conn'];
    //No category ID passed
    if (!isset($input['category_id']) && empty($input['category_id'])) {
        $results = $conn->query("SELECT h.id as home_id, h.name as home_name, h.image as home_image, m.id as main_id, home_category_id, m.name as main_name, m.image as main_image, m.icon as main_icon FROM main_categories m
        INNER JOIN home_categories h ON m.home_category_id = h.id");

        $re = mysqli_num_rows($results);

        if ($re > 0) {
            $all_rows = [];
            $type_id_name_map = [];
            $type_id_image_map = [];
            $distinct_type_ids = [];
            $home_cat = [];

            while ($row = mysqli_fetch_assoc($results)) {
                $all_rows[] = $row;

                $type_id_name_map[$row["home_category_id"]] = $row["home_name"];
                $type_id_image_map[$row["home_category_id"]] = $row["home_image"];

                $distinct_type_ids[] = $row["home_category_id"];
            }
            $response = null;
            $distinct_type_ids = array_values(array_unique($distinct_type_ids));

            foreach ($distinct_type_ids as $type_id) {
                $user_array = [];
                foreach ($all_rows as $current_row) {
                    if ($type_id === $current_row["home_category_id"]) {
                        $user_array[] = array(
                            "mainCategoryId" => (int) $current_row['main_id'],
                            "mainName" => $current_row['main_name'],
                            "mainImage" => $current_row['main_image']
                        );
                    }
                }
                $home_cat[] = array(
                    "homeCategoryId" => (int) $type_id,
                    "homeName" => $type_id_name_map[$type_id],
                    "homeImage" => $type_id_image_map[$type_id],
                    "mainCategories" => $user_array
                );
            }


            $response["data"] = array("status" => 1, "message" => "All categories in Home Page", "homeCategories" => $home_cat);
            echo json_encode($response);
            die(mysqli_error($conn));
        } else {
            $errorData["data"] = array("status" => 0,   "message" => "There is no home categories or service categories");
            echo json_encode($errorData);

            die(mysqli_error($conn));
        }
    }
    //category ID passed//////////////////////
    else if (isset($input['category_id'])) {
        $input = json_decode(file_get_contents('php://input'), true);
        $category_id = (int) $input['category_id'];

        $check = mysqli_query($conn, "select mc.division, mc.id, mc.icon, mc.name, hc.name as hc_name from main_categories mc
                                        INNER JOIN home_categories hc
                                        ON mc.home_category_id = hc.id
                                        where mc.home_category_id= $category_id");
        if (mysqli_num_rows($check) < 1) {
            $errorData["data"] = array("status" => 0,   "message" => "No categories found");
            echo json_encode($errorData);

            die(mysqli_error($conn));
        }

        while ($row = mysqli_fetch_assoc($check)) {
            $homeCategoryName = $row['hc_name'];
            if ($category_id == 1) {
                $type = 1;
                $mainCategories[] = array(
                    "mainCategoryId" => (int) $row['id'],
                    "icon" => $row['icon'],
                    "title" => $row['name']
                );
            } else if ($category_id == 2) {
                $type = 2;
                $division = $row['division'];
                $main_category_id = (int) $row['id'];

                $sql2 = mysqli_query($conn, "select * from rate_visits where main_category_id = '$main_category_id' ORDER BY rate LIMIT 1");
                $row2 = mysqli_fetch_assoc($sql2);
                $visits = (int) $row2['visits'];
                $minutes_per_visit = (int) $row2['minutes_per_visit'] == 0 ? 1 : (int) $row2['minutes_per_visit'];

                $total_minutes = $visits * $minutes_per_visit;
                $hours = floor($total_minutes / 60);
                $minutes = ($time % 60);
                $final_time = $hours . " hours and " . $minutes . " minutes";

                $abc = $division == 'A' ? NULL : $total_minutes / 60;
                $description = $division == 'A' ? "Rs. " . (int) $row2['rate'] . " per year / " . $visits . " services" : "Rs. " . (int) $row2['rate'] . " per year / " . $row2['visits'] . " visits" . " / $abc Hours";


                $mainCategories[] = array(
                    "mainCategoryId" => (int) $row['id'],
                    "division" => $division,
                    "icon" => $row['icon'],
                    "title" => $row['name'],
                    "description" => $description
                );
            }
            // else if($category_id==3){
            //     $type=3;
            //     $main_category_id = (int) $row['id'];
            //     $sql2 = mysqli_query($conn,"select * from rate_visits where main_category_id = '$main_category_id' ORDER BY rate LIMIT 1");
            //     $row2=mysqli_fetch_assoc($sql2);
            //     $description = "Rs. ".(int)$row2['rate']." per year / ".$row2['visits']." visits";

            //     $mainCategories[] = array(
            //         "mainCategoryId" => (int) $row['id'],
            //         "icon" => $row['icon'],
            //         "title" => $row['name'],
            //         "description"=>$description
            // );
            // }

        }
        $response['data'] = array("status" => 1, "message" => "All main categories under home category ID : $category_id", "type" => $type, "homeCategoryId" => $category_id, "homeCategoryName" => $homeCategoryName, "mainCategories" => $mainCategories);
        echo json_encode($response);
    }

    // $conn = $GLOBALS['conn'];
    // $input = json_decode(file_get_contents('php://input'), true);

    // //no category ID passed
    // if (!isset($input['category_id']) && empty($input['category_id'])) {
    //     $check = mysqli_query($conn, "select * from main_categories");
    //     if (mysqli_num_rows($check) < 1) {
    //         $errorData["data"] = array("status"=>0,   "message" => "No categories found");
    //         echo json_encode($errorData);

    //         die(mysqli_error($conn));
    //     }

    //     while ($row = mysqli_fetch_assoc($check)) {
    //         $mainCategories[] = array(
    //             "mainCategoryId" => (int) $row['id'],
    //             "icon" => $row['icon'],
    //             "image"=> $row['image'],
    //             "title" => $row['name']
    //         );
    //     }
    //     $response['data'] = array("status"=>1, "message"=>"main categories", "mainCategories" => $mainCategories);
    //     echo json_encode($response);
    //     die(mysqli_error($conn));
    // }

    // /////////////////////////////////////////



    // //category ID passed//////////////////////
    // else if (isset($input['category_id'])) {
    //     $input = json_decode(file_get_contents('php://input'), true);
    //     $category_id = (int) $input['category_id'];
    //     $check = mysqli_query($conn, "select * from main_categories where id= $category_id  ");
    //     if (mysqli_num_rows($check) < 1) {
    //         $errorData["data"] = array("status"=>0,   "message" => "No categories found");
    //         echo json_encode($errorData);

    //         die(mysqli_error($conn));
    //     }

    //     while ($row = mysqli_fetch_assoc($check)) {
    //         $mainCategories[] = array(
    //             "mainCategoryId" => (int) $row['id'],
    //             "icon" => $row['icon'],
    //             "image"=> $row['image'],
    //             "title" => $row['name']
    //         );
    //     }
    //     $response['data'] = array("status"=>1,"message"=>"main categories", "mainCategories" => $mainCategories);
    //     echo json_encode($response);
    // }
}


function getSubCategories()
{


    $categoryid = (int) $_GET['category_id'];


    $conn = $GLOBALS['conn'];

    $mainCategoryId = '';
    $mainCategoryName = '';
    $mainCategoryLabel1 = '';;
    $mainCategoryLabel2 = '';
    $mainCategoryLabel3 = '';


    if (!empty($categoryid)) {
        $homeCategoryId = NULL;
        $main = mysqli_query($conn, "SELECT * from main_categories WHERE id = $categoryid LIMIT 1");
        if (mysqli_num_rows($main) > 0) {
            $rw = mysqli_fetch_assoc($main);
            $homeCategoryId = (int) $rw['home_category_id'];
            $mainCategoryId = $rw['id'];
            $mainCategoryName = $rw['name'];
            $mainCategoryLabel1 = $rw['label_1'];
            $mainCategoryLabel2 = $rw['label_2'];
            $mainCategoryLabel3 = $rw['label_3'];
            $division = $rw['division'];
        } else {
            $errorData["data"] = array("status" => 0,   "message" => "Invalid id given.");
            echo json_encode($errorData);
            die(mysqli_error($connect));
        }
        $categories = "select * from sub_categories where main_category_id = '$categoryid'";
        $sql = mysqli_query($conn, $categories);

        if ($homeCategoryId == 1) {
            ///////////////////////////////////////////TYPE 1///////////////////////////////////////////////////////////////
            while ($row = mysqli_fetch_assoc($sql)) {
                $subCategoryListArray[] = array(
                    "subCategoryId" => (int) $row['id'],
                    "subCategoryName" => $row['name'],
                    "subCategoryImage" => $row['image'],
                    "subCategoryIsDisplay" => $row['is_display'],
                    "subCategoryDate" => $row['date']
                );
            }

            $reviewsCheck = "select * from reviews where main_category_id = '$categoryid'";
            $sql = mysqli_query($conn, $reviewsCheck);
            $resultset = array();
            while ($row = mysqli_fetch_assoc($sql)) {
                $resultset[] = array(
                    "name" => $row['user_name'],
                    "stars" => $row['stars'],
                    "text" => $row['review_text']
                );
            }
            $response['data'] = array(
                "status" => 1,
                "message" => "Home Menu 1",
                "type" => 1,
                "mainCategoryId" => (int) $mainCategoryId,
                "mainCategoryName" => $mainCategoryName,
                "mainCategoryLabel1" => $mainCategoryLabel1,
                "mainCategoryLabel2" => $mainCategoryLabel2,
                "mainCategoryLabel3" => $mainCategoryLabel3,
                "description" => "",
                "subCategoryItems" => $subCategoryListArray,
                "reviews" => $resultset
            );
            echo json_encode($response);
            die();
        } else if ($homeCategoryId == 2) {
            ///////////////////////////////////////////TYPE 2///////////////////////////////////////////////////////////////
            $reviewsCheck = "select * from reviews where main_category_id = '$categoryid'";
            $sql = mysqli_query($conn, $reviewsCheck);
            $resultset = array();
            while ($row = mysqli_fetch_assoc($sql)) {
                $resultset[] = array(
                    "name" => $row['user_name'],
                    "stars" => $row['stars'],
                    "text" => $row['review_text']
                );
            }

            $sql = mysqli_query($conn, "select * from rate_visits where main_category_id = '$categoryid' ORDER BY rate LIMIT 1");
            $row = mysqli_fetch_assoc($sql);

            //////////////////////////////////////////////
            $sql2 = mysqli_query($conn, "select * from rate_visits where main_category_id = '$categoryid' ORDER BY rate LIMIT 1");
            $row2 = mysqli_fetch_assoc($sql2);
            $visits = (int) $row2['visits'];
            $minutes_per_visit = (int) $row2['minutes_per_visit'] == 0 ? 1 : (int) $row2['minutes_per_visit'];

            $total_minutes = $visits * $minutes_per_visit;
            $abc = $total_minutes / 60;

            $description2a = "Rs. " . (int) $row['rate'] . " per year / " . $row['visits'] . " visits" . " / $abc Hours";




            ///////////////////////////////////////
            $description2 = "Rs. " . (int) $row['rate'] . " per year / " . $row['visits'] . " services";


            $response['data'] = array(
                "status" => 1,
                "message" => "Home Menu 2",
                "type" => 2,
                "mainCategoryId" => (int) $mainCategoryId,
                "mainCategoryName" => $mainCategoryName,
                "description" => $division == 'A' ? $description2 : $description2a,
                "mainCategoryImage" => $rw['image'],
                "how" => $rw['how'],
                "about" => $rw['about'],
                "reviews" => $resultset
            );
            echo json_encode($response);
            die();
        } else if ($homeCategoryId == 3) {
            ///////////////////////////////////////////TYPE 3///////////////////////////////////////////////////////////////
            $reviewsCheck = "select * from reviews where main_category_id = '$categoryid'";
            $sql = mysqli_query($conn, $reviewsCheck);
            $resultset = array();
            while ($row = mysqli_fetch_assoc($sql)) {
                $resultset[] = array(
                    "name" => $row['user_name'],
                    "stars" => $row['stars'],
                    "text" => $row['review_text']
                );
            }

            $sql = mysqli_query($conn, "select * from rate_visits where main_category_id = '$categoryid' ORDER BY rate LIMIT 1");
            $row = mysqli_fetch_assoc($sql);
            $description = "Rs. " . (int) $row['rate'] . " per year / " . $row['visits'] . " visits";


            $response['data'] = array(
                "status" => 1,
                "message" => "Home Menu 3",
                "type" => 3,
                "mainCategoryId" => (int) $mainCategoryId,
                "mainCategoryName" => $mainCategoryName,
                "description" => $description,
                "mainCategoryImage" => $rw['image'],
                "how" => $rw['how'],
                "about" => $rw['about'],
                "reviews" => $resultset
            );
            echo json_encode($response);
            die();
        }
    } else {
        $errorData["data"] = array("status" => 0,   "message" => "category_id is not given");
        echo json_encode($errorData);
        die(mysqli_error($connect));
    }
}

function getReviews()
{

    $categoryid = $_GET['category_id'];

    $conn = $GLOBALS['conn'];
    if (!empty($categoryid)) {
        $categories = "select * from reviews where main_category_id = '$categoryid'";
        $sql = mysqli_query($conn, $categories);
        $resultset = array();
        while ($row = mysqli_fetch_assoc($sql)) {
            $resultset[] = array(
                "name" => $row['user_name'],
                "stars" => $row['stars'],
                "text" => $row['review_text']
            );
        }
        $response["data"] = array("status" => 1, "message" => "reviews", "reviews" => $resultset);
        echo json_encode($response);
    } else {
        $errorData["data"] = array("status" => 0,   "message" => "category ID missing");
        echo json_encode($errorData);

        die(mysqli_error($conn));
    }
}

function createReviews()
{
    $conn = $GLOBALS['conn'];
    $inputJSON = file_get_contents('php://input');
    $input = json_decode($inputJSON, TRUE);


    if (!isset($input['user_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "user id is missing");
        echo json_encode($errorData);

        die(mysqli_error($conn));
    }


    if (empty($input['user_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "user id is empty");
        echo json_encode($errorData);

        die(mysqli_error($conn));
    }
    $user_id = (int) $input['user_id'];
    validateUserId($conn, $user_id);

    $review_text = '';
    if (isset($input['review_text'])) {
        $review_text = $input['review_text'];
    }


    $stars = '';
    if (isset($input['stars'])) {
        $stars = $input['stars'];
    }

    if (isset($input['visit_id'])) {
        ////////////////////////////////////////VISIT ID is SET//////////////////////////////

        $visit_id = (int) $input['visit_id'];



        $check = mysqli_query($conn, "SELECT * FROM booking_item_23 WHERE id = $visit_id");
        $getdetails = mysqli_fetch_assoc($check);
        $booking_id = (int) $getdetails['booking_id'];
        $emp_id = (int) $getdetails['emp_id'];
        $main_category_id = (int) $getdetails['main_category_id'];

        $status = $getdetails['status'];
        if ($status != 'COMPLETED') {
            $errorData["data"] = array("message" => "this visit_id is not completed. So cannot submit feecback");
            echo json_encode($errorData);
            die(mysqli_error($conn));
        }


        $test = mysqli_query($conn, "SELECT user_id FROM booking WHERE id = $booking_id LIMIT 1");
        if (mysqli_num_rows($test) == 0) {
            $errorData["data"] = array("message" => "visit_id is not matching with user_id");
            echo json_encode($errorData);
            die(mysqli_error($conn));
        }

        $query2 = mysqli_query($conn, "SELECT * FROM user WHERE id = $user_id");
        if (mysqli_num_rows($query2) > 0) {
            $rw = mysqli_fetch_assoc($query2);
            $user_name = $rw['name'];

            $alreadyCheck = mysqli_query($conn, "SELECT id FROM reviews WHERE booking_id=$booking_id");
            if (mysqli_num_rows($alreadyCheck) != 0) {
                $errorData["data"] = array("message" => "Feedback already submitted for this details");
                echo json_encode($errorData);
                die(mysqli_error($conn));
            }


            $q = "INSERT INTO reviews(booking_id, visit_id, emp_id, main_category_id, user_name, stars, review_text)
                                                VALUES($booking_id, $visit_id, $emp_id, $main_category_id, '$user_name', '$stars', '$review_text' )";


            $sql = mysqli_query($conn, $q);
            if ($sql) {
                $response["data"] = array("status" => 1, "message" => "review stored successfully");
                echo json_encode($response);
                die(mysqli_error($conn));
            } else {
                $errorData["data"] = array("status" => 0, "code" => 500, "message" => "something went wrong in server");
                echo json_encode($errorData);

                die(mysqli_error($conn));
            }
        } else {
            $errorData["data"] = array("status" => 0,   "message" => "user ID is invalid");
            echo json_encode($errorData);

            die(mysqli_error($conn));
        }
    }



    ///////////////////////////VISIT ID not SET//////////////////////////////////////////////
    $booking_id = NULL;
    if (isset($input['booking_id'])) {
        $booking_id = (int) $input['booking_id'];
    }


    $q2 = "SELECT * FROM user WHERE id = $user_id";
    //CHECK FOR BLOCKED CUSTOMER
    $checkk = mysqli_query($conn, "SELECT * FROM user WHERE id=$user_id AND account_status = 'BLOCKED' ");
    if (mysqli_num_rows($checkk) > 0) {
        $errorData["data"] = array("status" => 0,   "message" => "This customer Account is Blocked");
        echo json_encode($errorData);
        die();
    }
    //END oF BLOCKED


    $query = mysqli_query($conn, $q2);
    if (mysqli_num_rows($query) > 0) {
        $rw = mysqli_fetch_assoc($query);
        $user_name = $rw['name'];

        $bookingdetails = mysqli_query($conn, "SELECT emp_id FROM booking WHERE id = $booking_id AND user_id = $user_id ");
        $abc = mysqli_fetch_assoc($bookingdetails);
        $emp_id = (int) $abc['emp_id'];

        $productcheck = mysqli_query($conn, "SELECT product_id FROM booking_item WHERE booking_id = $booking_id ");
        $ab = mysqli_fetch_assoc($productcheck);
        $product_id = (int) $ab['product_id'];

        $getNameCheck = mysqli_query($conn, "SELECT mc.id FROM products p INNER JOIN sub_categories sc ON p.category_id = sc.id 
                                            	INNER JOIN main_categories mc ON sc.main_category_id = mc.id
                                                	WHERE p.id=$product_id");
        $bbaa = mysqli_fetch_assoc($getNameCheck);
        $main_category_id = (int) $bbaa['id'];

        $q = "INSERT INTO reviews(booking_id, emp_id, main_category_id, user_name, stars, review_text)
                        VALUES($booking_id, $emp_id, $main_category_id, '$user_name', '$stars', '$review_text' )";


        $sql = mysqli_query($conn, $q);
        if ($sql) {
            $response["data"] = array("status" => 1, "message" => "review stored successfully");
            echo json_encode($response);
            die(mysqli_error($conn));
        } else {
            $errorData["data"] = array("status" => 0, "code" => 500, "message" => "something went wrong in server");
            echo json_encode($errorData);

            die(mysqli_error($conn));
        }
    } else {
        $errorData["data"] = array("status" => 0,   "message" => "user ID is invalid");
        echo json_encode($errorData);

        die(mysqli_error($conn));
    }
}



function getInfoapi()
{

    if (checkReqMethod() !== 'POST') {
        $errorData["data"] = array("status" => 0, "code" => 405, "message" => "This HTTP Method is Not Allowed");
        echo json_encode($errorData);
        header("HTTP/1.1 405");
        die(mysqli_error($connect));
    }

    $inputJSON = file_get_contents('php://input');

    $input = json_decode($inputJSON, TRUE);

    $categoryid = $input['category_id'];

    $conn = $GLOBALS['conn'];
    if (!empty($categoryid)) {
        $categories = "select * from main_categories where id = '$categoryid'";
        $sql = mysqli_query($conn, $categories);
        $resultset = array();
        while ($row = mysqli_fetch_assoc($sql)) {
            $resultset[] = array(
                "id" =>  $row['id'],
                "home_category_id" => $row['home_category_id'],
                "name" => $row['name'],
                "image" => $row['image'],
                "static_page" => $row['static_page']
            );
        }
    } else {
        $resultset = "Please Select Category";
    }
    $data = [
        "status" => 1,
        'data' => $resultset,
        'message' => 'SubCategories'
    ];

    echo json_encode($data);
}


function getMaincatdescription()
{

    if (checkReqMethod() !== 'POST') {
        $errorData["data"] = array("status" => 0, "code" => 405, "message" => "This HTTP Method is Not Allowed");
        echo json_encode($errorData);
        header("HTTP/1.1 405");
        die(mysqli_error($conn));
    }

    $inputJSON = file_get_contents('php://input');
    $input = json_decode($inputJSON, TRUE);
    $categoryid = (int) $input['category_id'];
    $conn = $GLOBALS['conn'];
    if (!empty($categoryid)) {
        $categories = "select description from main_categories where id = '$categoryid'";
        $sql = mysqli_query($conn, $categories);
        $resultset = array();
        while ($row = mysqli_fetch_assoc($sql)) {
            $resultset[] = $row;
        }
    } else {
        $resultset = "Please Select Category";
    }
    $data = [
        "status" => 1,
        'data' => $resultset,
        'message' => 'Main Category Description'
    ];
    echo json_encode($data);
}

function getProductsFromMainCategoryId()
{
    $conn = $GLOBALS['conn'];

    if (isset($_GET['category_id'])) {
        $category_id = (int) $_GET['category_id'];
        $results = $conn->query("SELECT sc.id as sub_category_id, sc.name as sub_category_name, p.id as product_id, product_title, product_description, p.price as price, p.date as product_date FROM sub_categories sc
        INNER JOIN products p ON p.category_id = sc.id WHERE sc.is_display='YES' AND p.is_display='YES' AND sc.main_category_id=$category_id ");

        $re = mysqli_num_rows($results);

        if ($re > 0) {
            $all_rows = [];
            $type_id_name_map = [];
            $distinct_type_ids = [];


            while ($row = mysqli_fetch_assoc($results)) {
                $all_rows[] = $row;

                $type_id_name_map[$row["sub_category_id"]] = $row["sub_category_name"];
                $distinct_type_ids[] = $row["sub_category_id"];
            }
            $response = null;
            $distinct_type_ids = array_values(array_unique($distinct_type_ids));

            foreach ($distinct_type_ids as $type_id) {
                $products = [];
                foreach ($all_rows as $current_row) {
                    if ($type_id === $current_row["sub_category_id"]) {
                        $products[] = array(
                            "productId" => (int) $current_row['product_id'],
                            "productTitle" => $current_row['product_title'],
                            "productDescription" => $current_row['product_description'],
                            "productPrice" => $current_row['price'],
                            "productDate" => $current_row['product_date']
                        );
                    }
                }
                $subCategories[] = array(
                    "title" => $type_id_name_map[$type_id],
                    "products" => $products
                );
            }


            $response["data"] = $subCategories;
            echo json_encode($response);
            die(mysqli_error($conn));
        } else {
            $errorData["data"] = array("message" => "No data available");
            echo json_encode($errorData);

            die(mysqli_error($conn));
        }
    } else {
        $errorData["data"] = array("message" => "No category_id passed");
        echo json_encode($errorData);

        die(mysqli_error($conn));
    }
}

function getProducts()
{

    $conn = $GLOBALS['conn'];

    if (!isset($_GET['category_id'])) {
        $results = $conn->query("SELECT sc.id as sub_category_id, sc.name as sub_category_name, p.id as product_id, product_title, product_description, p.price as price, p.date as product_date FROM sub_categories sc
        INNER JOIN products p ON p.category_id = sc.id WHERE sc.is_display='YES' AND p.is_display='YES' ");

        $re = mysqli_num_rows($results);

        if ($re > 0) {
            $all_rows = [];
            $type_id_name_map = [];
            $distinct_type_ids = [];


            while ($row = mysqli_fetch_assoc($results)) {
                $all_rows[] = $row;

                $type_id_name_map[$row["sub_category_id"]] = $row["sub_category_name"];
                $distinct_type_ids[] = $row["sub_category_id"];
            }
            $response = null;
            $distinct_type_ids = array_values(array_unique($distinct_type_ids));

            foreach ($distinct_type_ids as $type_id) {
                $products = [];
                foreach ($all_rows as $current_row) {
                    if ($type_id === $current_row["sub_category_id"]) {
                        $products[] = array(
                            "productId" => (int) $current_row['product_id'],
                            "productTitle" => $current_row['product_title'],
                            "productDescription" => $current_row['product_description'],
                            "productPrice" => $current_row['price'],
                            "productDate" => $current_row['product_date']
                        );
                    }
                }
                $subCategories[] = array(
                    "title" => $type_id_name_map[$type_id],
                    "products" => $products
                );
            }


            $response["data"] = $subCategories;
            echo json_encode($response);
            die(mysqli_error($conn));
        } else {
            $errorData["data"] = array("message" => "No data available");
            echo json_encode($errorData);

            die(mysqli_error($conn));
        }
    } else if (isset($_GET['category_id'])) {
        $category_id = (int) $_GET['category_id'];
        $results = mysqli_query($conn, "SELECT * FROM  products p  WHERE p.is_display='YES' AND p.category_id=$category_id");
        $products = array();
        while ($row  = mysqli_fetch_assoc($results)) {
            $products[] = array(
                "productId" => (int) $row['id'],
                "productTitle" => $row['product_title'],
                "productDescription" => $row['product_description'],
                "productPrice" => $row['price'],
                "productDate" => $row['date']
            );
        }
        $response["data"] = array(
            "status" => 1,
            "message" => "products for the sub category",
            "subCategoryId" => $category_id,
            "products" => $products
        );
        echo json_encode($response);
        die(mysqli_error($conn));
    }
}

function getAnnualsubscription()
{

    $inputJSON = file_get_contents('php://input');

    $input = json_decode($inputJSON, TRUE);


    $conn = $GLOBALS['conn'];

    $subscription = "select * from subscription";

    $sql = mysqli_query($conn, $subscription);

    $resultset = array();

    while ($row = mysqli_fetch_assoc($sql)) {
        $resultset[] = $row;
    }

    $data = [
        'status' => 1,
        'data' => $resultset,
        'message' => 'Categories'
    ];

    echo json_encode($data);
}

function slots_old()
{
    $conn = $GLOBALS['conn'];

    date_default_timezone_set('Asia/Calcutta');
    $today =  date('Y-m-d');

    $datetime = new DateTime(date('Y-m-d'));
    $datetime->modify('+1 day');
    $tomorrow =  $datetime->format('Y-m-d');

    $datetime = new DateTime(date('Y-m-d'));
    $datetime->modify('+2 day');
    $dayaftertomorrow =  $datetime->format('Y-m-d');


    $result = mysqli_query($conn, "SELECT * from slot");
    if (mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $details = NULL;
            $start = $row['time'];
            $start_time = substr($start, 0, 8);
            if (($row['start'] == '07:00:00') || ($row['start'] == '09:00:00')) {
                $slots[] = array(
                    "id" => (int) $row['id'],
                    "name" => $row['name'],
                    "period" => "Morning"
                );
            } else if (($row['start'] == '11:00:00') || ($row['start'] == '13:00:00')) {
                $slots[] = array(
                    "id" => (int) $row['id'],
                    "name" => $row['name'],
                    "period" => "Afternoon"
                );
            } else if (($row['start'] == '15:00:00') || ($row['start'] == '17:00:00') || ($row['start'] == '19:00:00')) {
                $slots[] = array(
                    "id" => (int) $row['id'],
                    "name" => $row['name'],
                    "period" => "Evening"
                );
            }
        }
        $response['data'] = array(
            "status" => 1,
            "message" => "All slots",
            "today" => array("date" => $today, "slots" => $slots),
            "tomorrow" => array("date" => $tomorrow, "slots" => $slots),
            "dayaftertomorrow" => array("date" => $dayaftertomorrow, "slots" => $slots)
        );
        echo json_encode($response);
        die();
    } else {
        $errorData["data"] = array("status" => 0,   "message" => "No slots found");
        echo json_encode($errorData);

        die();
    }
}



function servicerDetails()
{
    $conn = $GLOBALS['conn'];
    $emp_id = (int) $_GET['emp_id'];
    checkEmployeeId($conn, $emp_id);
    $sql = mysqli_query($conn, "select * from employee where id= '$emp_id'");
    $employee = mysqli_fetch_object($sql);
    $data = [
        "message" => "servicer details",
        'status' => 1,
        'employee' => $employee
    ];

    echo json_encode($data);
}

function servicerSendOTP()
{
    $conn = $GLOBALS['conn'];
    $input = json_decode(file_get_contents('php://input'), true);
    $cell = $input['cell'];
    $country_code = NULL;
    $country_code = $input['code'];

    $mobileregex = "/^[6-9][0-9]{9}$/";
    if (!isset($input["cell"]) || empty($input["cell"]) || preg_match($mobileregex, $input["cell"]) == 0) {
        $errorData = null;
        $errorData["data"] = array("status" => 0,   "message" => "Cell is invalid");
        echo json_encode($errorData);

        die();
    }

    $result = mysqli_query($conn, "SELECT * FROM employee WHERE cell='$cell' AND country_code = '$country_code' LIMIT 1");
    if (mysqli_num_rows($result) > 0) {
        //Check whether EXISTING EMPLOYEE

        //CHECK FOR BLOCKED CUSTOMER
        $checkk = mysqli_query($conn, "SELECT * FROM employee WHERE cell='$cell' AND account_status = 'BLOCKED' ");
        if (mysqli_num_rows($checkk) > 0) {
            $errorData["data"] = array("status" => 0,   "message" => "This employee Account is Blocked");
            echo json_encode($errorData);
            die();
        }
        //END oF BLOCKED


        $thisrow = mysqli_fetch_assoc($result);
        $is_register_count = (int) $thisrow['is_register_complete'];
        $otp = 12345;
        $q1 = mysqli_query($conn, "DELETE FROM otp WHERE cell = '$cell' ");
        $q2 = mysqli_query($conn, "INSERT INTO otp(otp, country_code, cell)VALUES('$otp','$country_code', '$cell') ");
        if ($q2) {
            $response["data"] = array("status" => 1, "message" => "OTP sent successfully", "is_register_count" => $is_register_count);
            echo json_encode($response);
            die();
        }
    } else {
        $errorData["data"] = array("status" => 0,   "message" => "Phone number is unregistered");
        echo json_encode($errorData);

        die();
    }
}


function servicerVerifyOTP()
{
    $conn = $GLOBALS['conn'];
    $input = json_decode(file_get_contents('php://input'), true);
    $cell = $input['cell'];
    $otp = $input['otp'];
    $fcm_id = $input['fcm_id'];

    $mobileregex = "/^[6-9][0-9]{9}$/";
    if (!isset($input["cell"]) || empty($input["cell"]) || preg_match($mobileregex, $input["cell"]) == 0) {
        $errorData = null;
        $errorData["data"] = array("status" => 0,   "message" => "Phone number is invalid");
        echo json_encode($errorData);

        die();
    }

    if (mysqli_num_rows(mysqli_query($conn, "SELECT * FROM otp WHERE cell='$cell' AND otp='$otp' LIMIT 1")) > 0) {
        $codeCheck = mysqli_query($conn, "SELECT country_code FROM otp WHERE cell='$cell' AND otp='$otp' LIMIT 1");
        $rw = mysqli_fetch_assoc($codeCheck);
        $country_code = $rw['country_code'];
        $result = mysqli_query($conn, "SELECT * FROM employee WHERE cell='$cell' AND country_code = '$country_code' LIMIT 1");
        if (mysqli_num_rows($result) > 0) {
            //EXISTING EMPLOYEE

            $insert_fcmID = mysqli_query($conn, "UPDATE employee SET fcm_id = '$fcm_id' WHERE cell='$cell' ");
            //CHECK FOR BLOCKED CUSTOMER
            $checkk = mysqli_query($conn, "SELECT * FROM employee WHERE cell='$cell' AND account_status = 'BLOCKED' ");
            if (mysqli_num_rows($checkk) > 0) {
                $errorData["data"] = array("status" => 0,   "message" => "This employee Account is Blocked");
                echo json_encode($errorData);
                die();
            }
            //END oF BLOCKED



            $employee = mysqli_fetch_object($result);

            $data = [

                'status' => 1,
                "message" => "OTP verified, Login successful",
                'data' => $employee
            ];

            echo json_encode($data);
            $conn->query("DELETE FROM otp WHERE otp = $otp AND cell='$cell' AND country_code = '$country_code' ");
            die(mysqli_error($conn));
        } else {
            $conn->query("DELETE FROM otp WHERE otp = $otp AND cell='$cell' AND country_code = '$country_code' ");
            $response["data"] = array("status" => 0, "message" => "Mobile number unregistered");
            echo json_encode($response);

            die();
        }
    } else {
        $response["data"] = array("status" => 0, "message" => "Invalid data");
        echo json_encode($response);

        die();
    }
}




function servicerResendOTP()
{
    $conn = $GLOBALS['conn'];
    $input = json_decode(file_get_contents('php://input'), true);
    $cell = $input['cell'];
    $mobileregex = "/^[6-9][0-9]{9}$/";
    if (!isset($input["cell"]) || empty($input["cell"]) || preg_match($mobileregex, $input["cell"]) == 0) {
        $errorData = null;
        $errorData["data"] = array("status" => 0,   "message" => "Phone number is invalid");
        echo json_encode($errorData);

        die();
    }
    $otp = 12345;
    $q1 = mysqli_query($conn, "DELETE FROM otp WHERE cell = '$cell' ");
    $q2 = mysqli_query($conn, "INSERT INTO otp(otp, cell)VALUES('$otp', '$cell') ");
    if ($q2) {
        $response["data"] = array("status" => 1, "message" => "OTP Resent successfully");
        echo json_encode($response);
        die();
    }
}


function servicerUploadStep1()
{

    $inputJSON = file_get_contents('php://input');

    $input = json_decode($inputJSON, TRUE);
    $conn = $GLOBALS['conn'];

    if (isset($input['emp_id']) && !empty($input['emp_id'])) {
        $emp_id = (int) $input['emp_id'];

        //CHECK FOR BLOCKED CUSTOMER
        $checkk = mysqli_query($conn, "SELECT * FROM employee WHERE id='$emp_id' AND account_status = 'BLOCKED' ");
        if (mysqli_num_rows($checkk) > 0) {
            $errorData["data"] = array("status" => 0,   "message" => "This employee Account is Blocked");
            echo json_encode($errorData);
            die();
        }
        //END oF BLOCKED




        $name = $input['name'];
        $email = $input['email'];
        // Validate email 

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $response["data"] = array("status" => 0, "code" => 400, "message" => "Invalid email format");
            echo json_encode($response);
            die();
        }

        $country_code = $input['code'];
        $cell = $input['cell'];

        $mobileregex = "/^[6-9][0-9]{9}$/";
        if (!isset($input["cell"]) || empty($input["cell"]) || preg_match($mobileregex, $input["cell"]) == 0) {
            $errorData = null;
            $errorData["data"] = array("status" => 0,   "message" => "Cell is invalid");
            echo json_encode($errorData);

            die();
        }


        $dob = $input['dob'];
        $gender = $input['gender'];
        $house = $input['house'];
        $locality = $input['locality'];
        $city = $input['city'];
        $pincode = $input['pincode'];
        $res_addr = $house . " " . $locality . " " . $city . " " . $pincode;

        $query = "UPDATE employee SET 
                            name = '$name',
                            email = '$email',
                            country_code = '$country_code',
                            cell = '$cell',
                            dob = '$dob',
                            gender = '$gender',
                            res_addr = '$res_addr'
                        WHERE id = '$emp_id'";
        if (mysqli_query($conn, $query)) {

            $userquery = "select * from employee where id= $emp_id ";

            $sql = mysqli_query($conn, $userquery);

            $user = mysqli_fetch_object($sql);


            echo json_encode(array(
                'status' => 1,
                'message' => 'Step 1 Details uploaded successfully',
                "data" => $user
            ));
        } else {

            echo json_encode(array(
                'status' => 0,
                'message' => 'Something went to wrong'
            ));
        }
    } else if (!isset($input['emp_id'])) {
        $conn = $GLOBALS['conn'];


        $name = $input['name'];
        $email = $input['email'];
        // Validate email 

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $response["data"] = array("status" => 0, "code" => 400, "message" => "Invalid email format");
            echo json_encode($response);
            die();
        }

        $country_code = $input['code'];
        $cell = $input['cell'];

        $mobileregex = "/^[6-9][0-9]{9}$/";
        if (!isset($input["cell"]) || empty($input["cell"]) || preg_match($mobileregex, $input["cell"]) == 0) {
            $errorData = null;
            $errorData["data"] = array("status" => 0,   "message" => "Cell is invalid");
            echo json_encode($errorData);

            die();
        }

        //check if again tried with existing cell number
        $checkcell = mysqli_query($conn, "SELECT * FROM employee WHERE cell = $cell ");
        if (mysqli_num_rows($checkcell) > 0) {
            $response["data"] = array("status" => 0, "message" => "This mobile number is already registered");
            echo json_encode($response);
            die();
        }


        $dob = $input['dob'];
        $gender = $input['gender'];
        $house = $input['house'];
        $locality = $input['locality'];
        $city = $input['city'];
        $pincode = $input['pincode'];
        $res_addr = $house . " " . $locality . " " . $city . " " . $pincode;

        if (mysqli_query($conn, "INSERT INTO employee ( name, email,country_code,cell,dob,gender,res_addr,  is_register_complete)
                                                VALUES ('$name','$email','$country_code','$cell','$dob','$gender','$res_addr',1)")) {


            $emp_id = $conn->insert_id;

            $userquery = "select * from employee where id= $emp_id ";

            $sql = mysqli_query($conn, $userquery);

            $user = mysqli_fetch_object($sql);


            echo json_encode(array(
                'status' => 1,
                'message' => 'Step 1 Details uploaded successfully',
                "data" => $user
            ));
        } else {
            echo json_encode(array(
                'status' => 0,
                'message' => 'Something went to wrongg'
            ));
        }
    }
}



function servicerUploadStep2()
{

    $emp_id = (int) $_GET['emp_id'];
    $conn = $GLOBALS['conn'];

    //CHECK FOR BLOCKED CUSTOMER
    $checkk = mysqli_query($conn, "SELECT * FROM employee WHERE id='$emp_id' AND account_status = 'BLOCKED' ");
    if (mysqli_num_rows($checkk) > 0) {
        $errorData["data"] = array("status" => 0,   "message" => "This employee Account is Blocked");
        echo json_encode($errorData);
        die();
    }
    //END oF BLOCKED


    checkEmployeeId($conn, $emp_id);

    $checkemp = mysqli_query($conn, "SELECT * from employee WHERE id = $emp_id");
    if (mysqli_num_rows($checkemp) < 1) {
        $errorData["data"] = array("status" => 0,   "message" => "emp_id is invalid");
        echo json_encode($errorData);

        die();
    }

    $checking = mysqli_fetch_assoc($checkemp);
    $is_register_complete = 0;
    $is_register_complete = (int) $checking['is_register_complete'];

    //Uploading Images
    date_default_timezone_set('Asia/Calcutta');
    $datetime = date('dmY_hisA_');
    //image upload
    $file_name = "img_" . $datetime . "proof1_front" . ".jpg";
    $file_path = "./images/" . $file_name;
    if ($_FILES["proof1_front"]["name"]) {
        $info = pathinfo($_FILES['proof1_front']['name']);
        move_uploaded_file($_FILES['proof1_front']['tmp_name'], $file_path);


        $server_name = "https://" . $_SERVER['SERVER_NAME'];
        $folder_name = dirname($_SERVER['PHP_SELF']) . "/images/";
        $proof1_front_url = $server_name . $folder_name . $file_name;
    } else {
        $errorData["data"] = array("status" => 0,   "message" => "image is invalid");
        echo json_encode($errorData);

        die();
    }

    //////////////
    //image upload
    $file_name = "img_" . $datetime . "proof1_back" . ".jpg";
    $file_path = "./images/" . $file_name;
    if ($_FILES["proof1_back"]["name"]) {
        $info = pathinfo($_FILES['proof1_back']['name']);
        move_uploaded_file($_FILES['proof1_back']['tmp_name'], $file_path);

        $server_name = "https://" . $_SERVER['SERVER_NAME'];
        $folder_name = dirname($_SERVER['PHP_SELF']) . "/images/";
        $proof1_back_url = $server_name . $folder_name . $file_name;
    } else {
        $errorData["data"] = array("status" => 0,   "message" => "image is invalid");
        echo json_encode($errorData);

        die();
    }

    ////////////////////
    //image upload
    $file_name = "img_" . $datetime . "proof2_front" . ".jpg";
    $file_path = "./images/" . $file_name;
    if ($_FILES["proof2_front"]["name"]) {
        $info = pathinfo($_FILES['proof2_front']['name']);
        move_uploaded_file($_FILES['proof2_front']['tmp_name'], $file_path);

        $server_name = "https://" . $_SERVER['SERVER_NAME'];
        $folder_name = dirname($_SERVER['PHP_SELF']) . "/images/";
        $proof2_front_url = $server_name . $folder_name . $file_name;
    } else {
        $errorData["data"] = array("status" => 0,   "message" => "image is invalid");
        echo json_encode($errorData);

        die();
    }


    ////////////////
    //image upload
    $file_name = "img_" . $datetime . "proof2_back" . ".jpg";
    $file_path = "./images/" . $file_name;
    if ($_FILES["proof2_back"]["name"]) {
        $info = pathinfo($_FILES['proof2_back']['name']);
        move_uploaded_file($_FILES['proof2_back']['tmp_name'], $file_path);

        $server_name = "https://" . $_SERVER['SERVER_NAME'];
        $folder_name = dirname($_SERVER['PHP_SELF']) . "/images/";
        $proof2_back_url = $server_name . $folder_name . $file_name;
    } else {
        $errorData["data"] = array("status" => 0,   "message" => "image is invalid");
        echo json_encode($errorData);

        die();
    }


    ////////////////

    date_default_timezone_set('Asia/Calcutta');
    $updated_on = date('d F,Y h:i:s A');

    if ($is_register_complete > 2) {
        $q1 = mysqli_query($conn, "UPDATE employee SET proof1_front = '$proof1_front_url', proof1_back = '$proof1_back_url',  proof2_front = '$proof2_front_url',  proof2_back = '$proof2_back_url' 
                    WHERE id = $emp_id  ");
        if ($q1) {
            $userquery = "select * from employee where id= $emp_id ";

            $sql = mysqli_query($conn, $userquery);

            $user = mysqli_fetch_object($sql);


            echo json_encode(array(
                'status' => 1,
                'message' => 'Step 2 Details uploaded successfully',
                "data" => $user
            ));
        } else {

            echo json_encode(array(
                'status' => 0,
                'message' => 'Something went to wrong'
            ));
        }
    } else if ($is_register_complete <= 2) {
        $q1 = mysqli_query($conn, "UPDATE employee SET is_register_complete = 2, proof1_front = '$proof1_front_url', proof1_back = '$proof1_back_url',  proof2_front = '$proof2_front_url',  proof2_back = '$proof2_back_url' 
                    WHERE id = $emp_id  ");
        if ($q1) {
            $userquery = "select * from employee where id= $emp_id ";

            $sql = mysqli_query($conn, $userquery);

            $user = mysqli_fetch_object($sql);


            echo json_encode(array(
                'status' => 1,
                'message' => 'Step 2 Details uploaded successfully',
                "data" => $user
            ));
        } else {

            echo json_encode(array(
                'status' => 0,
                'message' => 'Something went to wrong'
            ));
        }
    }
}


function servicerUploadStep3()
{

    $inputJSON = file_get_contents('php://input');

    $input = json_decode($inputJSON, TRUE);
    $conn = $GLOBALS['conn'];


    $emp_id = (int) $input['emp_id'];

    //CHECK FOR BLOCKED CUSTOMER
    $checkk = mysqli_query($conn, "SELECT * FROM employee WHERE id='$emp_id' AND account_status = 'BLOCKED' ");
    if (mysqli_num_rows($checkk) > 0) {
        $errorData["data"] = array("status" => 0,   "message" => "This employee Account is Blocked");
        echo json_encode($errorData);
        die();
    }
    //END oF BLOCKED


    $house = $input['house'];
    $locality = $input['locality'];
    $city = $input['city'];
    $pincode = $input['pincode'];
    $state = $input['state'];

    $current_addr = $house . " " . $locality . " " . $city . " " . $pincode . " " . $state;

    checkEmployeeId($conn, $emp_id);

    $checkemp = mysqli_query($conn, "SELECT * from employee WHERE id = $emp_id");
    $checking = mysqli_fetch_assoc($checkemp);
    $is_register_complete = 0;
    $is_register_complete = (int) $checking['is_register_complete'];


    if ($is_register_complete > 3) {
        $query = "UPDATE employee SET 
            current_addr = '$current_addr'
                WHERE id = $emp_id";
        if (mysqli_query($conn, $query)) {

            $userquery = "select * from employee where id= $emp_id";

            $sql = mysqli_query($conn, $userquery);

            $user = mysqli_fetch_object($sql);


            echo json_encode(array(
                'status' => 1,
                'message' => 'Step 3 Details uploaded successfully',
                "data" => $user
            ));
        } else {

            echo json_encode(array(
                'status' => 0,
                'message' => 'Something went to wrong'
            ));
        }
    } else  if ($is_register_complete <= 3) {
        $query = "UPDATE employee SET 
            current_addr = '$current_addr', is_register_complete=3
                WHERE id = $emp_id";
        if (mysqli_query($conn, $query)) {

            $userquery = "select * from employee where id= $emp_id";

            $sql = mysqli_query($conn, $userquery);

            $user = mysqli_fetch_object($sql);


            echo json_encode(array(
                'status' => 1,
                'message' => 'Step 3 Details uploaded successfully',
                "data" => $user
            ));
        } else {

            echo json_encode(array(
                'status' => 0,
                'message' => 'Something went to wrong'
            ));
        }
    }
}


function mainCategoryNames()
{

    $conn = $GLOBALS['conn'];

    $results = $conn->query("SELECT m.id as mc_id, m.name as mc_name, s.id as sc_id, s.name as sc_name FROM main_categories m
        INNER JOIN sub_categories s ON s.main_category_id = m.id");

    $re = mysqli_num_rows($results);

    if ($re > 0) {
        $all_rows = [];
        $type_id_name_map = [];
        $type_id_image_map = [];
        $distinct_type_ids = [];
        $main_cat = [];
        while ($row = mysqli_fetch_assoc($results)) {
            $all_rows[] = $row;
            $type_id_name_map[$row["mc_id"]] = $row["mc_name"];
            $distinct_type_ids[] = $row["mc_id"];
        }
        $response = null;
        $distinct_type_ids = array_values(array_unique($distinct_type_ids));

        foreach ($distinct_type_ids as $type_id) {
            $user_array = [];
            foreach ($all_rows as $current_row) {
                if ($type_id === $current_row["mc_id"]) {
                    $user_array[] = array(
                        "subCategoryId" => (int) $current_row['sc_id'],
                        "subCategoryName" => $current_row['sc_name']
                    );
                }
            }
            $main_cat[] = array(
                "mainCategoryId" => (int) $type_id,
                "mainCategoryName" => $type_id_name_map[$type_id],
                "mainCategories" => $user_array
            );
        }


        $response["data"] = array("status" => 1, "message" => "main categories", "mainCategories" => $main_cat);
        echo json_encode($response);
        die(mysqli_error($conn));
    } else {
        $errorData["data"] = array("status" => 0,   "message" => "There is main categories in DB");
        echo json_encode($errorData);

        die(mysqli_error($conn));
    }
}


function servicerUploadStep4()
{

    $inputJSON = file_get_contents('php://input');

    $input = json_decode($inputJSON, TRUE);
    $conn = $GLOBALS['conn'];

    $emp_id = (int) $input['emp_id'];

    //CHECK FOR BLOCKED CUSTOMER
    $checkk = mysqli_query($conn, "SELECT * FROM employee WHERE id='$emp_id' AND account_status = 'BLOCKED' ");
    if (mysqli_num_rows($checkk) > 0) {
        $errorData["data"] = array("status" => 0,   "message" => "This employee Account is Blocked");
        echo json_encode($errorData);
        die();
    }
    //END oF BLOCKED


    checkEmployeeId($conn, $emp_id);

    $main_category_services = $input['main_category_services'];
    $sub_category_services = $input['sub_category_services'];


    $checkemp = mysqli_query($conn, "SELECT * from employee WHERE id = $emp_id");
    $checking = mysqli_fetch_assoc($checkemp);
    $is_register_complete = 0;
    $is_register_complete = (int) $checking['is_register_complete'];


    if ($is_register_complete > 4) {
        $query = "UPDATE employee SET
            main_category_services = '$main_category_services',
            sub_category_services = '$sub_category_services'
                WHERE id = $emp_id";
        if (mysqli_query($conn, $query)) {

            $userquery = "select * from employee where id= $emp_id";

            $sql = mysqli_query($conn, $userquery);

            $user = mysqli_fetch_object($sql);


            echo json_encode(array(
                'status' => 1,
                'message' => 'Step 4 Details uploaded successfully',
                "data" => $user
            ));
        } else {

            echo json_encode(array(
                'status' => 0,
                'message' => 'Something went to wrong'
            ));
        }
    } else if ($is_register_complete <= 4) {
        $query = "UPDATE employee SET
            is_register_complete = 4,
            main_category_services = '$main_category_services',
            sub_category_services = '$sub_category_services'
                WHERE id = $emp_id";
        if (mysqli_query($conn, $query)) {

            $userquery = "select * from employee where id= $emp_id";

            $sql = mysqli_query($conn, $userquery);

            $user = mysqli_fetch_object($sql);


            echo json_encode(array(
                'status' => 1,
                'message' => 'Step 4 Details uploaded successfully',
                "data" => $user
            ));
        } else {

            echo json_encode(array(
                'status' => 0,
                'message' => 'Something went to wrong'
            ));
        }
    }
}

function zoneNames()
{
    $conn = $GLOBALS['conn'];
    $result = mysqli_query($conn, "SELECT id, name from zones");
    if (mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $zones[] = array(
                "zoneId" => (int) $row['id'],
                "zoneName" => $row['name'],
            );
        }
        $response['data'] = array(
            "status" => 1,
            "zones" => $zones
        );
        echo json_encode($response);
        die();
    } else {
        $errorData["data"] = array("status" => 0,   "message" => "No zones found");
        echo json_encode($errorData);

        die();
    }
}

function servicerUploadServiceZone()
{

    $inputJSON = file_get_contents('php://input');

    $input = json_decode($inputJSON, TRUE);
    $conn = $GLOBALS['conn'];

    $emp_id = (int) $input['emp_id'];
    //CHECK FOR BLOCKED CUSTOMER
    $checkk = mysqli_query($conn, "SELECT * FROM employee WHERE id='$emp_id' AND account_status = 'BLOCKED' ");
    if (mysqli_num_rows($checkk) > 0) {
        $errorData["data"] = array("status" => 0,   "message" => "This employee Account is Blocked");
        echo json_encode($errorData);
        die();
    }
    //END oF BLOCKED
    checkEmployeeId($conn, $emp_id);

    $zone_id = (int) $input['zone_id'];
    checkZoneId($conn, $zone_id);


    $checkemp = mysqli_query($conn, "SELECT * from employee WHERE id = $emp_id");
    $checking = mysqli_fetch_assoc($checkemp);
    $is_register_complete = 0;
    $is_register_complete = (int) $checking['is_register_complete'];


    if ($is_register_complete > 5) {
        $query = "UPDATE employee SET
            zone_id = '$zone_id'
                WHERE id = $emp_id";
        if (mysqli_query($conn, $query)) {

            $userquery = "select * from employee where id= $emp_id";

            $sql = mysqli_query($conn, $userquery);

            $user = mysqli_fetch_object($sql);


            echo json_encode(array(
                'status' => 1,
                'message' => 'Service Zone Details uploaded successfully',
                "data" => $user
            ));
        } else {

            echo json_encode(array(
                'status' => 0,
                'message' => 'Something went to wrong'
            ));
        }
    } else if ($is_register_complete <= 5) {
        $query = "UPDATE employee SET 
            is_register_complete = 5,
            zone_id = '$zone_id'
                 WHERE id = $emp_id";
        if (mysqli_query($conn, $query)) {

            $userquery = "select * from employee where id= $emp_id";

            $sql = mysqli_query($conn, $userquery);

            $user = mysqli_fetch_object($sql);


            echo json_encode(array(
                'status' => 1,
                'message' => 'Service Zone Details uploaded successfully',
                "data" => $user
            ));
        } else {

            echo json_encode(array(
                'status' => 0,
                'message' => 'Something went to wrong'
            ));
        }
    }
}

function servicerDeclarationsList()
{
    $conn = $GLOBALS['conn'];
    $result = mysqli_query($conn, "SELECT term1, term2, term3 from declaration");
    if (mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $terms[] = array(
                "term1" => $row['term1'],
                "term2" => $row['term2'],
                "term3" => $row['term3']

            );
        }
        $response['data'] = array(
            "status" => 1,
            "message" => "terms and conditions",
            "terms" => $terms
        );
        echo json_encode($response);
        die();
    } else {
        $errorData["data"] = array("status" => 0,   "message" => "No Declarations found");
        echo json_encode($errorData);

        die();
    }
}



function servicerUploadStep5()
{

    $inputJSON = file_get_contents('php://input');

    $input = json_decode($inputJSON, TRUE);
    $conn = $GLOBALS['conn'];

    $emp_id = (int) $input['emp_id'];
    //CHECK FOR BLOCKED CUSTOMER
    $checkk = mysqli_query($conn, "SELECT * FROM employee WHERE id='$emp_id' AND account_status = 'BLOCKED' ");
    if (mysqli_num_rows($checkk) > 0) {
        $errorData["data"] = array("status" => 0,   "message" => "This employee Account is Blocked");
        echo json_encode($errorData);
        die();
    }
    //END oF BLOCKED
    checkEmployeeId($conn, $emp_id);

    $query = "UPDATE employee SET 
            is_register_complete = 6
        WHERE id = $emp_id";
    if (mysqli_query($conn, $query)) {

        $userquery = "select * from employee where id= $emp_id";

        $sql = mysqli_query($conn, $userquery);

        $user = mysqli_fetch_object($sql);


        echo json_encode(array(
            'status' => 1,
            'message' => 'Step 5 Details uploaded successfully',
            "data" => $user
        ));
    } else {

        echo json_encode(array(
            'status' => 0,
            'message' => 'Something went to wrong'
        ));
    }
}

function servicerCheckApproved()
{
    $conn = $GLOBALS['conn'];
    $emp_id = (int) $_GET['emp_id'];
    //CHECK FOR BLOCKED CUSTOMER
    $checkk = mysqli_query($conn, "SELECT * FROM employee WHERE id='$emp_id' AND account_status = 'BLOCKED' ");
    if (mysqli_num_rows($checkk) > 0) {
        $errorData["data"] = array("status" => 0,   "message" => "This employee Account is Blocked");
        echo json_encode($errorData);
        die();
    }
    //END oF BLOCKED
    checkEmployeeId($conn, $emp_id);
    $result = mysqli_query($conn, "SELECT id, approval from employee WHERE id = $emp_id");
    if (mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        $response['data'] = array(
            "status" => 1,
            "message" => "account approval status",
            "emp_id" => $row['id'],
            "approval" => $row['approval']
        );
        echo json_encode($response);
        die();
    } else {
        $errorData["data"] = array("status" => 0,   "message" => "Invalid emp_id");
        echo json_encode($errorData);

        die();
    }
}

function servicerCreditHistory()
{
    $conn = $GLOBALS['conn'];
    $emp_id = (int) $_GET['emp_id'];
    //CHECK FOR BLOCKED CUSTOMER
    $checkk = mysqli_query($conn, "SELECT * FROM employee WHERE id='$emp_id' AND account_status = 'BLOCKED' ");
    if (mysqli_num_rows($checkk) > 0) {
        $errorData["data"] = array("status" => 0,   "message" => "This employee Account is Blocked");
        echo json_encode($errorData);
        die();
    }
    //END oF BLOCKED
    checkEmployeeId($conn, $emp_id);
    $recharges = null;
    $expenses = NULL;
    $all = NULL;
    $penalties = NULL;
    $credits = 0;
    $result = mysqli_query($conn, "SELECT * FROM `servicer_credit_history` WHERE emp_id = $emp_id ORDER BY id DESC");
    if (mysqli_num_rows($result) > 0) {
        $q1 = mysqli_query($conn, "select sum(credits) as totalrecharges from servicer_credit_history WHERE type=1");
        $r1 = mysqli_fetch_assoc($q1);
        $totalrecharges = (int) $r1['totalrecharges'];

        $q2 = mysqli_query($conn, "select sum(credits) as totalexpenses from servicer_credit_history WHERE type=2");
        $r2 = mysqli_fetch_assoc($q2);
        $totalexpenses = (int) $r2['totalexpenses'];

        $q3 = mysqli_query($conn, "select sum(credits) as totalpenalties from servicer_credit_history WHERE type=3");
        $r3 = mysqli_fetch_assoc($q3);
        $totalpenalties = (int) $r3['totalpenalties'];


        $credits = $totalrecharges - $totalexpenses - $totalpenalties;


        while ($row = mysqli_fetch_assoc($result)) {

            if ($row['type'] == 1) {
                $recharges[] = array(
                    "id" => (int) $row['id'],
                    "type" => "recharges",
                    "date" => $row['added_on'],
                    "amount" => (int) $row['amount'],
                    "credits" => (int) $row['credits'],
                    "status" => $row['status']
                );
                $all[] = array(
                    "id" => (int) $row['id'],
                    "type" => "recharges",
                    "date" => $row['added_on'],
                    "amount" => (int) $row['amount'],
                    "credits" => (int) $row['credits'],
                    "status" => $row['status']
                );
            }

            if ($row['type'] == 2) {
                $expenses[] = array(
                    "id" => (int) $row['id'],
                    "type" => "expenses",
                    "date" => $row['added_on'],
                    "booking_id" => (int) $row['type2_booking_id'],
                    "credits" => (int) $row['credits'],
                    "status" => $row['status']
                );
                $all[] = array(
                    "id" => (int) $row['id'],
                    "type" => "expenses",
                    "date" => $row['added_on'],
                    "booking_id" => (int) $row['type2_booking_id'],
                    "credits" => (int) $row['credits'],
                    "status" => $row['status']
                );
            }

            if ($row['type'] == 3) {
                $penalties[] = array(
                    "id" => (int) $row['id'],
                    "type" => "penalties",
                    "date" => $row['added_on'],
                    "credits" => (int) $row['credits'],
                    "status" => $row['status']
                );
                $all[] = array(
                    "id" => (int) $row['id'],
                    "type" => "penalties",
                    "date" => $row['added_on'],
                    "credits" => (int) $row['credits'],
                    "status" => $row['status']
                );
            }
        }

        $response['data'] = array(
            "status" => 1,
            "message" => "credit history",
            "emp_id" => $emp_id,
            "total_credits" => $credits,
            "all" =>  $all,
            "recharges" =>  $recharges,
            "expenses" => $expenses,
            "penalties" => $penalties
        );
        echo json_encode($response);
        die();
    } else {
        $errorData["data"] = array("status" => 0,   "message" => "No credit history found");
        echo json_encode($errorData);

        die();
    }
}


function servicerPackages()
{
    $conn = $GLOBALS['conn'];

    $result = mysqli_query($conn, "SELECT id, name, amount, credits, amount_per_credit  FROM `servicer_packages` WHERE validity='YES' ");
    if (mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $packages = array(
                "id" => (int) $row['id'],
                "name" => $row['name'],
                "amount" => (int) $row['amount'],
                "credits" => (int) $row['credits'],
                "amount_per_credit" => (int) $row['amount_per_credit']
            );
        }
        $response['data'] = array(
            "status" => 1,
            "message" => "packages found",
            "packages" => $packages
        );
        echo json_encode($response);
        die();
    } else {
        $errorData["data"] = array("status" => 0,   "message" => "No packages found in DB");
        echo json_encode($errorData);

        die();
    }
}


function getSubcatlist()
{
    $conn = $GLOBALS['conn'];
    $categoryid = $input['category_id'];
    $results = $conn->query("SELECT sub.id as sub_id
            , sub.name as subname
            , sub.image as subimage
            , sub.main_category_id as product_id
            , p.id as product_id
            , p.category_id as category_id
            , p.product_title as product_title
            , p.product_description as product_description
            , p.price as product_price FROM sub_categories sub
        INNER JOIN products p ON sub.id = p.category_id WHERE sub.id = $categoryid");
    $re = mysqli_num_rows($results);
    if ($re > 0) {
        $all_rows = [];
        $type_id_name_map = [];
        $type_id_image_map = [];
        $distinct_type_ids = [];
        $home_cat = [];
        while ($row = mysqli_fetch_assoc($results)) {
            $all_rows[] = $row;

            $type_id_name_map[$row["sub_id"]] = $row["sub_id"];
            $type_id_image_map[$row["home_category_id"]] = $row["subname"];
            $type_id_image_map[$row["home_category_id"]] = $row["subimage"];
            $distinct_type_ids[] = $row["sub_id"];
        }
        $response = null;
        $distinct_type_ids = array_values(array_unique($distinct_type_ids));

        foreach ($distinct_type_ids as $type_id) {
            $user_array = [];
            foreach ($all_rows as $current_row) {
                if ($type_id === $current_row["sub_id"]) {
                    $user_array[] = array(
                        "mainCategoryId" => (int) $current_row['product_id'],
                        "mainName" => $current_row['product_title'],
                        "mainImage" => $current_row['product_description'],
                        "mainPrice" => $current_row['product_price']
                    );
                }
            }
            $home_cat[] = array(
                "homeCategoryId" => (int) $type_id,
                "homeName" => $type_id_name_map[$type_id],
                "homeImage" => $type_id_image_map[$type_id],
                "mainCategories" => $user_array
            );
        }


        $response["data"] = array("status" => 1, "message" => "home categories", "homeCategories" => $home_cat);
        echo json_encode($response, JSON_PRETTY_PRINT);
        die(mysqli_error($conn));
    } else {
        $errorData["data"] = array("status" => 0,   "message" => "There is no home categories or service categories");
        echo json_encode($errorData);

        die(mysqli_error($conn));
    }
}



function addToCartCount()
{
    $conn = $GLOBALS['conn'];
    $inputJSON = file_get_contents('php://input');
    $input = json_decode($inputJSON, TRUE);
    if (!isset($input['user_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No user_id is supplied");
        echo json_encode($errorData);
        die();
    }
    if (empty($input['user_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No user_id is supplied");
        echo json_encode($errorData);

        die();
    }
     $user_id = (int) $input['user_id'];
validateUserId($conn, $user_id);
    if (!isset($input['type'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No type is supplied");
        echo json_encode($errorData);
        die();
    }
    if (empty($input['type'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No type is supplied");
        echo json_encode($errorData);

        die();
    }


   
    $type = $input['type'];
    //CHECK FOR BLOCKED CUSTOMER
    $checkk = mysqli_query($conn, "SELECT * FROM user WHERE id='$user_id' AND account_status IN('BLOCKED','INACTIVE')  ");
    if (mysqli_num_rows($checkk) > 0) {
        $errorData["data"] = array("status" => 0,   "message" => "This customer Account is Blocked or Inactive");
        echo json_encode($errorData);
        die();
    }
    //END oF BLOCKED
    if ($type == '1') {
        ////////////////////////////////////////////////////////////////HOME TYPE 1//////////////////////////////////////////////////////////////
        if (!isset($input['product_id'])) {
            $errorData["data"] = array("status" => 0,   "message" => "No product_id is supplied");
            echo json_encode($errorData);
            die();
        }
        if (empty($input['product_id'])) {
            $errorData["data"] = array("status" => 0,   "message" => "No product_id is supplied");
            echo json_encode($errorData);

            die();
        }
        $product_id = (int) $input['product_id'];

        $checkname = mysqli_query($conn, "SELECT product_title,price, product_description from products WHERE id = $product_id");
        $rrr = mysqli_fetch_assoc($checkname);
        $product_price = (int) $rrr['price'];
        $product_title = $rrr['product_title'];
        $product_description = $rrr['product_description'];

        $checkproductname = mysqli_query($conn, "SELECT mc.name FROM main_categories mc INNER JOIN sub_categories sc ON sc.main_category_id=mc.id
                                    INNER JOIN products p on p.category_id = sc.id AND p.id=$product_id ");
        $rw = mysqli_fetch_assoc($checkproductname);
        $mainCategoryName = $rw['name'];

        $check = mysqli_query($conn, "SELECT * from cart WHERE user_id = $user_id");
        if (mysqli_num_rows($check) > 0) {
            //already some cart items are there for this user
            $updated = date('Y-m-d');

            $rw = mysqli_fetch_assoc($check);
            $cart_id = (int) $rw['id'];
            $estimate = (int) $rw['estimate'];
            $user_address_id = (int) $rw['user_address_id'];
            $date = $rw['date'];
            $slot_id = (int) $rw['slot_id'];



            $checkcartitem = mysqli_query($conn, "SELECT * FROM cart_item WHERE cart_id = $cart_id AND product_id = $product_id");
            if (mysqli_num_rows($checkcartitem) > 0) {
                //same product_id already in cart for this user
                $cur_row = mysqli_fetch_assoc($checkcartitem);

                $productprice = (int) $cur_row['price'];
                $productcount = (int) $cur_row['count'];

                $pricePerItem = $productprice / $productcount;
                $updatedPrice = $productprice + $pricePerItem;



                $one = mysqli_query($conn, "UPDATE cart_item SET count=$productcount+1, price=$updatedPrice WHERE product_id = $product_id AND cart_id=$cart_id");

                $checkcarttotalprice = mysqli_query($conn, "SELECT sum(price) as estimate FROM cart_item WHERE cart_id=$cart_id");
                $rr = mysqli_fetch_assoc($checkcarttotalprice);
                $estimate = (int) $rr['estimate'];

                $two = mysqli_query($conn, "UPDATE cart SET estimate=$estimate WHERE id = $cart_id AND user_id=$user_id");

                if ($one and $two) {
                    $cartitemcheck = mysqli_query($conn, "SELECT * from cart_item WHERE cart_id=$cart_id ");
                    $cartitems = [];
                    while ($rrow = mysqli_fetch_assoc($cartitemcheck)) {
                        $productId = (int) $rrow['product_id'];
                        $checkname = mysqli_query($conn, "SELECT product_title, product_description from products WHERE id = $productId");
                        $r = mysqli_fetch_assoc($checkname);
                        $product_title = $r['product_title'];
                        $product_description = $r['product_description'];

                        $checkproductname = mysqli_query($conn, "SELECT mc.name FROM main_categories mc INNER JOIN sub_categories sc ON sc.main_category_id=mc.id
                                                INNER JOIN products p on p.category_id = sc.id AND p.id=$productId ");
                        $rw = mysqli_fetch_assoc($checkproductname);
                        $mainCategoryName = $rw['name'];

                        $cartitems[] = array(
                            "cartItemId" => (int) $rrow['id'],
                            "productId" => (int) $rrow['product_id'],
                            "productTitle" => $product_title,
                            "productDescription" => $product_description,
                            "mainCategoryName" => $mainCategoryName,
                            "count" => (int) $rrow['count'],
                            "price" => (int) $rrow['price']
                        );
                    }

                    $cartcheck = mysqli_query($conn, "SELECT * from cart WHERE id=$cart_id ");
                    $cartrow = mysqli_fetch_assoc($cartcheck);
                    $response["data"] = array(
                        "status" => 1,
                        "message" => "Item added to cart successfully",
                        "type" => $type,
                        "user_id" => $user_id,
                        "cartId" => (int) $cartrow['id'],
                        "estimate" => (int) $cartrow['estimate'],
                        "user_address_id" => (int) $cartrow['user_address_id'],
                        "date" => $cartrow['date'],
                        "slot_id" => (int) $cartrow['slot_id'],
                        "cartItems" => $cartitems
                    );
                    echo json_encode($response);
                    die(mysqli_error($conn));
                }
                //
                else {

                    $errorData["data"] = array("status" => 0, "code" => 500, "message" => "Something went wrong in server");
                    echo json_encode($errorData);

                    die(mysqli_error($conn));
                }
            } else  if (mysqli_num_rows($checkcartitem) < 1) {

                //check previously added cart items' main category
                $getidcheck = mysqli_query($conn, "SELECT * from cart_item WHERE cart_id=$cart_id LIMIT 1");
                $aabb = mysqli_fetch_assoc($getidcheck);
                $previousAddedProductId = (int) $aabb['product_id'];
                $getidcheck = mysqli_query($conn, "SELECT mc.id FROM products p INNER JOIN sub_categories sc ON p.category_id = sc.id 
                                            	INNER JOIN main_categories mc ON sc.main_category_id = mc.id
                                                	WHERE p.id=$previousAddedProductId");
                $bbaa = mysqli_fetch_Assoc($getidcheck);
                $previousProductsCategoryId = (int) $bbaa['id'];

                $getidcheck = mysqli_query($conn, "SELECT mc.id FROM products p INNER JOIN sub_categories sc ON p.category_id = sc.id 
                                            	INNER JOIN main_categories mc ON sc.main_category_id = mc.id
                                                	WHERE p.id=$product_id");
                $bbaa = mysqli_fetch_assoc($getidcheck);
                $newProductCategoryId = (int) $bbaa['id'];

                if ($newProductCategoryId != $previousProductsCategoryId) {
                    $errorData["data"] = array("status" => 0, "message" => "This product is from new category. Do you wish to clear cart and add this product?");
                    echo json_encode($errorData);
                    die();
                }

                //check previously added cart items' main category END HERE

                $insertcartitem = mysqli_query($conn, "INSERT INTO cart_item(cart_id, product_id, count, price)
                                                    VALUES($cart_id,$product_id,1,$product_price )");
                $cart_item_id = $conn->insert_id;

                $checkcarttotalprice = mysqli_query($conn, "SELECT sum(price) as estimate FROM cart_item WHERE cart_id=$cart_id");
                $rr = mysqli_fetch_assoc($checkcarttotalprice);
                $estimate = (int) $rr['estimate'];

                $updatecartquery = mysqli_query($conn, "UPDATE cart SET estimate=$estimate WHERE id = $cart_id AND user_id=$user_id");

                if ($insertcartitem and $updatecartquery) {
                    $cartitemcheck = mysqli_query($conn, "SELECT * from cart_item WHERE cart_id=$cart_id ");
                    $cartitems = [];
                    while ($rrow = mysqli_fetch_assoc($cartitemcheck)) {
                        $productId = (int) $rrow['product_id'];
                        $checkname = mysqli_query($conn, "SELECT product_title, product_description from products WHERE id = $productId");
                        $r = mysqli_fetch_assoc($checkname);
                        $product_title = $r['product_title'];
                        $product_description = $r['product_description'];

                        $checkproductname = mysqli_query($conn, "SELECT mc.name FROM main_categories mc INNER JOIN sub_categories sc ON sc.main_category_id=mc.id
                                                INNER JOIN products p on p.category_id = sc.id AND p.id=$productId ");
                        $rw = mysqli_fetch_assoc($checkproductname);
                        $mainCategoryName = $rw['name'];

                        $cartitems[] = array(
                            "cartItemId" => (int) $rrow['id'],
                            "productId" => (int) $rrow['product_id'],
                            "productTitle" => $product_title,
                            "productDescription" => $product_description,
                            "mainCategoryName" => $mainCategoryName,
                            "count" => (int) $rrow['count'],
                            "price" => (int) $rrow['price']
                        );
                    }

                    $cartcheck = mysqli_query($conn, "SELECT * from cart WHERE id=$cart_id ");
                    $cartrow = mysqli_fetch_assoc($cartcheck);
                    $response["data"] = array(
                        "status" => 1,
                        "message" => "Item added to cart successfully",
                        "type" => $type,
                        "user_id" => $user_id,
                        "cartId" => (int) $cartrow['id'],
                        "estimate" => (int) $cartrow['estimate'],
                        "user_address_id" => (int) $cartrow['user_address_id'],
                        "date" => $cartrow['date'],
                        "slot_id" => (int) $cartrow['slot_id'],
                        "cartItems" => $cartitems
                    );
                    echo json_encode($response);
                    die(mysqli_error($conn));
                } else {
                    $errorData["data"] = array("status" => 0, "code" => 500, "message" => "Something went wrong in serverr");
                    echo json_encode($errorData);

                    die(mysqli_error($conn));
                }
            }
            //
        } else {
            $updated = date('Y-m-d');

            $priceCheck = mysqli_query($conn, "SELECT price from products WHERE id = $product_id");
            $rw = mysqli_fetch_assoc($priceCheck);
            $price = (int) $rw['price'];

            $res1 = mysqli_query($conn, "INSERT INTO cart(home_category_id, user_id, estimate) 
                        VALUES ($type, $user_id, $price )");
            $cart_id = (int) $conn->insert_id;

            $res = mysqli_query($conn, "INSERT INTO cart_item(cart_id, product_id, count,price) 
                        VALUES ( $cart_id, $product_id, 1, $price)");
            $cart_item_id =  (int) $conn->insert_id;
            if ($res and $res1) {
                $cartitemcheck = mysqli_query($conn, "SELECT * from cart_item WHERE cart_id=$cart_id ");
                while ($rw = mysqli_fetch_assoc($cartitemcheck)) {
                    $cartitems[] = array(
                        "cartItemId" => (int) $rw['id'],
                        "productId" => (int) $rw['product_id'],
                        "productTitle" => $product_title,
                        "productDescription" => $product_description,
                        "mainCategoryName" => $mainCategoryName,
                        "count" => (int) $rw['count'],
                        "price" => (int) $rw['price']
                    );
                }

                $cartcheck = mysqli_query($conn, "SELECT * from cart WHERE id=$cart_id ");
                while ($rw = mysqli_fetch_assoc($cartcheck)) {
                    $response["data"] = array(
                        "status" => 1,
                        "message" => "Item added to cart successfully",
                        "type" => $type,
                        "user_id" => $user_id,
                        "cartId" => (int) $rw['id'],
                        "estimate" => (int) $rw['estimate'],
                        "user_address_id" => (int) $rw['user_address_id'],
                        "date" => $rw['date'],
                        "slot_id" => (int) $rw['slot_id'],
                        "cartItems" => $cartitems
                    );
                    echo json_encode($response);
                    die(mysqli_error($conn));
                }
            }
            //
            else {
                $errorData["data"] = array("status" => 0, "code" => 500, "message" => "Something went wrong in serverr");
                echo json_encode($errorData);

                die(mysqli_error($conn));
            }
        }
    } else if ($type == '2') {
        ////////////////////////////////////////////////////////////////HOME TYPE 2//////////////////////////////////////////////////////////////



        if (!isset($input['tariff_id'])) {
            $errorData["data"] = array("status" => 0,   "message" => "No tariff_id is supplied");
            echo json_encode($errorData);
            die();
        }
        $tariff_id = (int) $input['tariff_id'];

        $test = mysqli_query($conn, "SELECT mc.home_category_id FROM main_categories mc
                                                        INNER JOIN rate_visits rv ON rv.main_category_id=mc.id
                                                        WHERE rv.id = $tariff_id");
        $row = mysqli_fetch_assoc($test);
        $home_category_id = (int) $row['home_category_id'];
        if ($home_category_id != $type) {
            $errorData["data"] = array("status" => 0,   "message" => "This tariff_id is not from type 2");
            echo json_encode($errorData);
            die();
        }

        if (!isset($input['quantity'])) {
            $errorData["data"] = array("status" => 0,   "message" => "No quantity is supplied");
            echo json_encode($errorData);
            die();
        }
        $quantity = (int) $input['quantity'];
        if ($quantity < 1) {
            $errorData["data"] = array("status" => 0,   "message" => "quantity is less than 1");
            echo json_encode($errorData);
            die();
        }

        $check = mysqli_query($conn, "SELECT * from cart WHERE user_id = $user_id");

        if (mysqli_num_rows($check) > 0) {
            //already some cart items are there for this user///////////////////////////////////////////////////////

            $rw = mysqli_fetch_assoc($check);
            $home_category_id = (int) $rw['home_category_id'];

            ///////////////////////////////////////////////

            $check = mysqli_query($conn, "SELECT c.home_category_id, ci.main_category_id from cart c INNER JOIN cart_item ci ON ci.cart_id = c.id WHERE c.user_id = $user_id");
            $rw = mysqli_fetch_assoc($check);
            $home_category_id = (int) $rw['home_category_id'];
            $main_category_id = (int) $rw['main_category_id'];

            $test2 = mysqli_query($conn, "SELECT division FROM main_categories WHERE id=$main_category_id ");
            $rww = mysqli_fetch_assoc($test2);
            $division = $rww['division'];

            ////////////////////////////////////////////////////////


            if ($division != 'A') {
                $errorData["data"] = array("status" => 0, "message" => "There is cart content from type B. Do you wish to clear cart and add this?");
                echo json_encode($errorData);
                die();
            }
            if ($home_category_id == $type) {
                $cart_id = (int) $rw['id'];
                $deleteQuery = mysqli_query($conn, "DELETE FROM cart WHERE id = $cart_id");
                ////////////////////////NOW cart is free for this user///////////////////////////////////////////////////////
            }

            $priceCheck = mysqli_query($conn, "SELECT rate,visits,main_category_id from rate_visits WHERE id = $tariff_id");
            if (mysqli_num_rows($priceCheck) == 0) {
                $errorData["data"] = array("status" => 0,   "message" => "No tariff available for this details");
                echo json_encode($errorData);
                die();
            }
            $rw = mysqli_fetch_assoc($priceCheck);
            $visits = (int) $rw['visits'];
            $main_category_id = (int) $rw['main_category_id'];


            $ratePerQuantity = (int) $rw['rate'];
            $estimate = $ratePerQuantity * $quantity;

            $res1 = mysqli_query($conn, "INSERT INTO cart(home_category_id, user_id, estimate) 
                                    VALUES ($type, $user_id, $estimate )");
            $cart_id = (int) $conn->insert_id;

            $res = mysqli_query($conn, "INSERT INTO cart_item(cart_id, main_category_id, count, price,visits) 
                                    VALUES ( $cart_id, $main_category_id, $quantity, $estimate, $visits)");
            $cart_item_id =  (int) $conn->insert_id;
            if ($res and $res1) {
                $response["data"] = array(
                    "status" => 1,
                    "message" => "Type 2 Item added to cart successfullyy",
                    "type" => $type
                );
                echo json_encode($response);
                die(mysqli_error($conn));
            }
            //
            else {
                $errorData["data"] = array("status" => 0, "code" => 500, "message" => "Something went wrong in server");
                echo json_encode($errorData);

                die(mysqli_error($conn));
            }
        } else {
            //cart is free for this user///////////////////////////////////////////////////////
            $priceCheck = mysqli_query($conn, "SELECT rate,visits,main_category_id from rate_visits WHERE id = $tariff_id");
            if (mysqli_num_rows($priceCheck) == 0) {
                $errorData["data"] = array("status" => 0,   "message" => "No tariff available for this details");
                echo json_encode($errorData);
                die();
            }
            $rw = mysqli_fetch_assoc($priceCheck);
            $visits = (int) $rw['visits'];
            $main_category_id = (int) $rw['main_category_id'];


            $ratePerQuantity = (int) $rw['rate'];
            $estimate = $ratePerQuantity * $quantity;

            $res1 = mysqli_query($conn, "INSERT INTO cart(home_category_id, user_id, estimate) 
                                    VALUES ($type, $user_id, $estimate )");
            $cart_id = (int) $conn->insert_id;

            $res = mysqli_query($conn, "INSERT INTO cart_item(cart_id, main_category_id, count, price,visits) 
                                    VALUES ( $cart_id, $main_category_id, $quantity, $estimate, $visits)");
            $cart_item_id =  (int) $conn->insert_id;
            if ($res and $res1) {
                $response["data"] = array(
                    "status" => 1,
                    "message" => "Type 2 Item added to cart successfully",
                    "type" => $type
                );
                echo json_encode($response);
                die(mysqli_error($conn));
            }
            //
            else {
                $errorData["data"] = array("status" => 0, "code" => 500, "message" => "Something went wrong in server");
                echo json_encode($errorData);

                die(mysqli_error($conn));
            }
        }
    } else if ($type == '2B') {
        ////////////////////////////////////////////////////////////////HOME TYPE 2B//////////////////////////////////////////////////////////////


        
        if (!isset($input['tariff_id'])) {
            $errorData["data"] = array("status" => 0,   "message" => "No tariff_id is supplied");
            echo json_encode($errorData);
            die();
        }
        $tariff_id = (int) $input['tariff_id'];



        $test = mysqli_query($conn, "SELECT mc.home_category_id, mc.division FROM main_categories mc
                                                        INNER JOIN rate_visits rv ON rv.main_category_id=mc.id
                                                        WHERE rv.id = $tariff_id AND mc.division='B' ");
        $row = mysqli_fetch_assoc($test);
        $division = $row['home_category_id'] . $row['division'];
        if ($division != $type) {
            $errorData["data"] = array("status" => 0,   "message" => "This tariff_id is not from type 2B");
            echo json_encode($errorData);
            die();
        }

        if (!isset($input['quantity'])) {
            $errorData["data"] = array("status" => 0,   "message" => "No quantity is supplied");
            echo json_encode($errorData);
            die();
        }
        $quantity = (int) $input['quantity'];
        if ($quantity < 1) {
            $errorData["data"] = array("status" => 0,   "message" => "quantity is less than 1");
            echo json_encode($errorData);
            die();
        }

        $check = mysqli_query($conn, "SELECT c.home_category_id, ci.main_category_id from cart c INNER JOIN cart_item ci ON ci.cart_id = c.id WHERE c.user_id = $user_id");
        $rw = mysqli_fetch_assoc($check);
        $home_category_id = (int) $rw['home_category_id'];
        $main_category_id = (int) $rw['main_category_id'];

        if (mysqli_num_rows($check) > 0) {
            //already some cart items are there for this user///////////////////////////////////////////////////////

            $test2 = mysqli_query($conn, "SELECT division FROM main_categories WHERE id=$main_category_id ");
            $rww = mysqli_fetch_assoc($test2);
            $division = $home_category_id . "" . $rww['division'];



            if ($division != $type) {
                $errorData["data"] = array("status" => 0, "message" => "There is already cart content from type 2A. Do you wish to clear cart and add this?");
                echo json_encode($errorData);
                die();
            }
            if ($division == $type) {
                $cart_id = (int) $rw['id'];
                $deleteQuery = mysqli_query($conn, "DELETE FROM cart WHERE id = $cart_id");
                ////////////////////////NOW cart is free for this user///////////////////////////////////////////////////////
            }

            $priceCheck = mysqli_query($conn, "SELECT rate,visits,main_category_id from rate_visits WHERE id = $tariff_id");
            if (mysqli_num_rows($priceCheck) == 0) {
                $errorData["data"] = array("status" => 0,   "message" => "No tariff available for this details");
                echo json_encode($errorData);
                die();
            }
            $rw = mysqli_fetch_assoc($priceCheck);
            $visits = (int) $rw['visits'];
            $main_category_id = (int) $rw['main_category_id'];


            $ratePerQuantity = (int) $rw['rate'];
            $estimate = $ratePerQuantity * $quantity;


            $home_category_id = substr($type, 0, 1);

            $res1 = mysqli_query($conn, "INSERT INTO cart(home_category_id, user_id, estimate) 
                                    VALUES ($home_category_id, $user_id, $estimate )");

            $cart_id = (int) $conn->insert_id;

            $res = mysqli_query($conn, "INSERT INTO cart_item(cart_id, main_category_id, count, price,visits) 
                                                            VALUES ( $cart_id, $main_category_id, $quantity, $estimate, $visits)");
            $cart_item_id =  (int) $conn->insert_id;


            if ($res and $res1) {
                $response["data"] = array(
                    "status" => 1,
                    "message" => "Type 2B Item added to cart successfully",
                    "type" => $type
                );
                echo json_encode($response);
                die(mysqli_error($conn));
            }
            //
            else {
                $errorData["data"] = array("status" => 0, "code" => 500, "message" => "Something went wrong in server");
                echo json_encode($errorData);

                die(mysqli_error($conn));
            }
        } else {
            //cart is free for this user///////////////////////////////////////////////////////
            $priceCheck = mysqli_query($conn, "SELECT rate,visits,main_category_id from rate_visits WHERE id = $tariff_id");
            if (mysqli_num_rows($priceCheck) == 0) {
                $errorData["data"] = array("status" => 0,   "message" => "No tariff available for this details");
                echo json_encode($errorData);
                die();
            }
            $rw = mysqli_fetch_assoc($priceCheck);
            $visits = (int) $rw['visits'];
            $main_category_id = (int) $rw['main_category_id'];


            $ratePerQuantity = (int) $rw['rate'];
            $estimate = $ratePerQuantity * $quantity;



            $home_category_id = substr($type, 0, 1);

            $res1 = mysqli_query($conn, "INSERT INTO cart(home_category_id, user_id, estimate) 
                                    VALUES ($home_category_id, $user_id, $estimate )");
            $cart_id = (int) $conn->insert_id;

            $res = mysqli_query($conn, "INSERT INTO cart_item(cart_id, main_category_id, count, price,visits) 
                                    VALUES ( $cart_id, $main_category_id, $quantity, $estimate, $visits)");
            $cart_item_id =  (int) $conn->insert_id;
            if ($res and $res1) {
                $response["data"] = array(
                    "status" => 1,
                    "message" => "Type 2B Item added to cart successfully",
                    "type" => $type
                );
                echo json_encode($response);
                die(mysqli_error($conn));
            }
            //
            else {
                $errorData["data"] = array("status" => 0, "code" => 500, "message" => "Something went wrong in server");
                echo json_encode($errorData);

                die(mysqli_error($conn));
            }
        }
    }
}





function deleteFromCartCount()
{
    $conn = $GLOBALS['conn'];


    $inputJSON = file_get_contents('php://input');

    $input = json_decode($inputJSON, TRUE);

    $user_id = (int) $input['user_id'];
    //CHECK FOR BLOCKED CUSTOMER
    $checkk = mysqli_query($conn, "SELECT * FROM user WHERE id='$user_id' AND account_status IN('BLOCKED','INACTIVE')  ");
    if (mysqli_num_rows($checkk) > 0) {
        $errorData["data"] = array("status" => 0,   "message" => "This customer Account is Blocked or Inactive");
        echo json_encode($errorData);
        die();
    }
    //END oF BLOCKED

    $product_id = (int) $input['product_id'];

    //
    $checkcart = mysqli_query($conn, "SELECT * from cart WHERE user_id = $user_id");


    if (mysqli_num_rows($checkcart) == 0) {
        $errorData["data"] = array("status" => 0,   "message" => "There is no cart items for this user.");
        echo json_encode($errorData);

        die(mysqli_error($conn));
    } else  if (mysqli_num_rows($checkcart) > 0) {
        $abc = mysqli_fetch_assoc($checkcart);
        $cart_id = (int) $abc['id'];

        $cartitemscounthere = mysqli_query($conn, "SELECT * FROM cart_item WHERE cart_id = $cart_id");
        if (mysqli_num_rows($cartitemscounthere) == 1) {
            //only this product is in cart
            $checkcartitem = mysqli_query($conn, "SELECT * from cart_item WHERE cart_id = $cart_id  AND product_id = $product_id LIMIT 1 ");
            if (mysqli_num_rows($checkcartitem) == 0) {
                $errorData["data"] = array("status" => 0,   "message" => "This product is not in the cart for current user.");
                echo json_encode($errorData);

                die(mysqli_error($conn));
            }
            //
            else if (mysqli_num_rows($checkcartitem) == 1) {
                $checkcartitemcount = mysqli_query($conn, "SELECT * from cart_item WHERE cart_id = $cart_id  AND product_id = $product_id ");
                $xyz = mysqli_fetch_assoc($checkcartitemcount);
                $count = (int) $xyz['count'];
                if ($count == 1) {
                    mysqli_query($conn, "DELETE FROM cart WHERE id = $cart_id");
                    $response["data"] = array("status" => 2, "message" => "Item deleted successfully, cart is empty now", "cart" => array());
                    echo json_encode($response);
                    die(mysqli_error($conn));
                }
                //
                else if ($count > 1) {
                    //same product_id already in cart for this user, count >1
                    $productprice = (int) $xyz['price'];
                    $productcount = (int) $xyz['count'];

                    $pricePerItem = $productprice / $productcount;
                    $updatedPrice = $productprice - $pricePerItem;

                    $one = mysqli_query($conn, "UPDATE cart_item SET count=$productcount-1, price=$updatedPrice WHERE product_id = $product_id AND cart_id=$cart_id");

                    $checkcarttotalprice = mysqli_query($conn, "SELECT sum(price) as estimate FROM cart_item WHERE cart_id=$cart_id");
                    $rr = mysqli_fetch_assoc($checkcarttotalprice);
                    $estimate = (int) $rr['estimate'];

                    $two = mysqli_query($conn, "UPDATE cart SET estimate=$estimate WHERE id = $cart_id AND user_id=$user_id");

                    if ($one and $two) {
                        $cartitemcheck = mysqli_query($conn, "SELECT * from cart_item WHERE cart_id=$cart_id ");
                        $cartitems = [];
                        while ($rrow = mysqli_fetch_assoc($cartitemcheck)) {
                            $productId = (int) $rrow['product_id'];
                            $checkname = mysqli_query($conn, "SELECT product_title, product_description from products WHERE id = $productId");
                            $r = mysqli_fetch_assoc($checkname);
                            $product_title = $r['product_title'];
                            $product_description = $r['product_description'];

                            $checkproductname = mysqli_query($conn, "SELECT mc.name FROM main_categories mc INNER JOIN sub_categories sc ON sc.main_category_id=mc.id
                                                                INNER JOIN products p on p.category_id = sc.id AND p.id=$productId ");
                            $rw = mysqli_fetch_assoc($checkproductname);
                            $mainCategoryName = $rw['name'];

                            $cartitems[] = array(
                                "cartItemId" => (int) $rrow['id'],
                                "productId" => (int) $rrow['product_id'],
                                "productTitle" => $product_title,
                                "productDescription" => $product_description,
                                "mainCategoryName" => $mainCategoryName,
                                "count" => (int) $rrow['count'],
                                "price" => (int) $rrow['price']
                            );
                        }

                        $cartcheck = mysqli_query($conn, "SELECT * from cart WHERE id=$cart_id ");
                        $cartrow = mysqli_fetch_assoc($cartcheck);
                        $response["data"] = array(
                            "status" => 1,
                            "message" => "Item deleted from cart successfully",
                            "user_id" => $user_id,
                            "cartId" => (int) $cartrow['id'],
                            "estimate" => (int) $cartrow['estimate'],
                            "user_address_id" => (int) $cartrow['user_address_id'],
                            "date" => $cartrow['date'],
                            "slot_id" => (int) $cartrow['slot_id'],
                            "cartItems" => $cartitems
                        );
                        echo json_encode($response);
                        die(mysqli_error($conn));
                    } else {
                        $errorData["data"] = array("status" => 0, "code" => 500, "message" => "Something went wrong in serverr");
                        echo json_encode($errorData);

                        die(mysqli_error($conn));
                    }
                }
            }
        }
        //
        else if (mysqli_num_rows($cartitemscounthere) > 1) {
            //not only this item, some other items also there in cart for this user
            $checkcartitem = mysqli_query($conn, "SELECT * from cart_item WHERE cart_id = $cart_id  AND product_id = $product_id LIMIT 1 ");
            if (mysqli_num_rows($checkcartitem) == 0) {
                $errorData["data"] = array("status" => 0,   "message" => "This product is not in the cart for current user.");
                echo json_encode($errorData);

                die(mysqli_error($conn));
            }
            //
            else if (mysqli_num_rows($checkcartitem) == 1) {
                $checkcartitemcount = mysqli_query($conn, "SELECT * from cart_item WHERE cart_id = $cart_id  AND product_id = $product_id ");
                $xyz = mysqli_fetch_assoc($checkcartitemcount);
                $count = (int) $xyz['count'];
                $price = (int) $xyz['price'];
                if ($count == 1) {
                    mysqli_query($conn, "DELETE FROM cart_item WHERE cart_id = $cart_id  AND product_id = $product_id");

                    mysqli_query($conn, "UPDATE cart SET estimate = estimate-$price WHERE user_id = $user_id");

                    $cartitemcheck = mysqli_query($conn, "SELECT * from cart_item WHERE cart_id=$cart_id ");
                    $cartitems = [];
                    while ($rrow = mysqli_fetch_assoc($cartitemcheck)) {
                        $productId = (int) $rrow['product_id'];
                        $checkname = mysqli_query($conn, "SELECT product_title, product_description from products WHERE id = $productId");
                        $r = mysqli_fetch_assoc($checkname);
                        $product_title = $r['product_title'];
                        $product_description = $r['product_description'];

                        $checkproductname = mysqli_query($conn, "SELECT mc.name FROM main_categories mc INNER JOIN sub_categories sc ON sc.main_category_id=mc.id
                                                                INNER JOIN products p on p.category_id = sc.id AND p.id=$productId ");
                        $rw = mysqli_fetch_assoc($checkproductname);
                        $mainCategoryName = $rw['name'];

                        $cartitems[] = array(
                            "cartItemId" => (int) $rrow['id'],
                            "productId" => (int) $rrow['product_id'],
                            "productTitle" => $product_title,
                            "productDescription" => $product_description,
                            "mainCategoryName" => $mainCategoryName,
                            "count" => (int) $rrow['count'],
                            "price" => (int) $rrow['price']
                        );
                    }

                    $cartcheck = mysqli_query($conn, "SELECT * from cart WHERE id=$cart_id ");
                    $cartrow = mysqli_fetch_assoc($cartcheck);
                    $response["data"] = array(
                        "status" => 1,
                        "message" => "Item deleted from cart successfully",
                        "user_id" => $user_id,
                        "cartId" => (int) $cartrow['id'],
                        "estimate" => (int) $cartrow['estimate'],
                        "user_address_id" => (int) $cartrow['user_address_id'],
                        "date" => $cartrow['date'],
                        "slot_id" => (int) $cartrow['slot_id'],
                        "cartItems" => $cartitems
                    );
                    echo json_encode($response);
                    die(mysqli_error($conn));
                }
                //
                else if ($count > 1) {
                    //same product_id already in cart for this user, count >1
                    $productprice = (int) $xyz['price'];
                    $productcount = (int) $xyz['count'];

                    $pricePerItem = $productprice / $productcount;
                    $updatedPrice = $productprice - $pricePerItem;

                    $one = mysqli_query($conn, "UPDATE cart_item SET count=$productcount-1, price=$updatedPrice WHERE product_id = $product_id AND cart_id=$cart_id");

                    $checkcarttotalprice = mysqli_query($conn, "SELECT sum(price) as estimate FROM cart_item WHERE cart_id=$cart_id");
                    $rr = mysqli_fetch_assoc($checkcarttotalprice);
                    $estimate = (int) $rr['estimate'];

                    $two = mysqli_query($conn, "UPDATE cart SET estimate=$estimate WHERE id = $cart_id AND user_id=$user_id");

                    if ($one and $two) {
                        $cartitemcheck = mysqli_query($conn, "SELECT * from cart_item WHERE cart_id=$cart_id ");
                        $cartitems = [];
                        while ($rrow = mysqli_fetch_assoc($cartitemcheck)) {
                            $productId = (int) $rrow['product_id'];
                            $checkname = mysqli_query($conn, "SELECT product_title, product_description from products WHERE id = $productId");
                            $r = mysqli_fetch_assoc($checkname);
                            $product_title = $r['product_title'];
                            $product_description = $r['product_description'];

                            $checkproductname = mysqli_query($conn, "SELECT mc.name FROM main_categories mc INNER JOIN sub_categories sc ON sc.main_category_id=mc.id
                                                                INNER JOIN products p on p.category_id = sc.id AND p.id=$productId ");
                            $rw = mysqli_fetch_assoc($checkproductname);
                            $mainCategoryName = $rw['name'];

                            $cartitems[] = array(
                                "cartItemId" => (int) $rrow['id'],
                                "productId" => (int) $rrow['product_id'],
                                "productTitle" => $product_title,
                                "productDescription" => $product_description,
                                "mainCategoryName" => $mainCategoryName,
                                "count" => (int) $rrow['count'],
                                "price" => (int) $rrow['price']
                            );
                        }

                        $cartcheck = mysqli_query($conn, "SELECT * from cart WHERE id=$cart_id ");
                        $cartrow = mysqli_fetch_assoc($cartcheck);
                        $response["data"] = array(
                            "status" => 1,
                            "message" => "Item deleted from cart successfully",
                            "user_id" => $user_id,
                            "cartId" => (int) $cartrow['id'],
                            "estimate" => (int) $cartrow['estimate'],
                            "user_address_id" => (int) $cartrow['user_address_id'],
                            "date" => $cartrow['date'],
                            "slot_id" => (int) $cartrow['slot_id'],
                            "cartItems" => $cartitems
                        );
                        echo json_encode($response);
                        die(mysqli_error($conn));
                    }
                }
            }
        }
    }
}
///////////////////////////////////////////AFTER CHAOS//////////////////////////////////////////////////////////////////////

function customerChoosePaymentType()
{
    $conn = $GLOBALS['conn'];
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['user_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No user_id is supplied");
        echo json_encode($errorData);

        die();
    }
    if (empty($input['user_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No user_id is supplied");
        echo json_encode($errorData);

        die();
    }

    if (!isset($input['payment_type'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No payment_type is supplied");
        echo json_encode($errorData);

        die();
    }
    if (empty($input['payment_type'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No payment_type is supplied");
        echo json_encode($errorData);

        die();
    }

    if (!isset($input['booking_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No booking_id is supplied");
        echo json_encode($errorData);

        die();
    }
    if (empty($input['booking_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No booking_id is supplied");
        echo json_encode($errorData);

        die();
    }


    $user_id = (int) $input['user_id'];
    $booking_id = (int) $input['booking_id'];
    $payment_type = $input['payment_type'];
    // // echo $payment_type;
    // $type1 = "ONLINE";
    // $type2 = "CASH";

    // if (($payment_type!= $type1) || ($payment_type!= $type2)) {
    //     // echo $payment_type;
    //     $errorData["data"] = array("status" => 0,   "message" => "Invalid payment_type is supplied");
    //     echo json_encode($errorData);
    //     die();
    // }

    //CHECK FOR BLOCKED CUSTOMER
    $checkk = mysqli_query($conn, "SELECT * FROM user WHERE id='$user_id' AND account_status IN('BLOCKED','INACTIVE')  ");
    if (mysqli_num_rows($checkk) > 0) {
        $errorData["data"] = array("status" => 0,   "message" => "This customer Account is Blocked or Inactive");
        echo json_encode($errorData);
        die();
    }
    //END oF BLOCKED
    validateUserId($conn, $user_id);

    $check = mysqli_query($conn, "SELECT * FROM booking WHERE user_id=$user_id AND id = $booking_id AND servicer_status IN('Completed') AND payment IN('PENDING')");
    if (mysqli_num_rows($check) == 0) {
        $errorData["data"] = array("status" => 0,   "message" => "Invalid inputs");
        echo json_encode($errorData);
        die();
    }

    $q1 = mysqli_query($conn, "UPDATE booking SET payment_type = '$payment_type' WHERE user_id=$user_id AND id = $booking_id AND servicer_status IN('Completed') AND payment IN('PENDING') ");


    if ($q1) {

        $check = mysqli_query($conn, "SELECT * FROM `booking` WHERE id=$booking_id AND payment_type='' ");
        if (mysqli_num_rows($check)) {
            $errorData["data"] = array("status" => 0,   "message" => "Payment type choosing failed. Server error!");
            echo json_encode($errorData);
            die();
        }

        $errorData["data"] = array("status" => 1,   "message" => "Payment type chosen successfully");
        echo json_encode($errorData);
        die();
    } else {
        $errorData["data"] = array("status" => 0,   "message" => "Payment type choosing failed. Server error!");
        echo json_encode($errorData);
        die();
    }
}


function deleteCustomerAddress()
{
    $conn = $GLOBALS['conn'];
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['user_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No user_id is supplied");
        echo json_encode($errorData);

        die();
    }
    if (empty($input['user_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No user_id is supplied");
        echo json_encode($errorData);

        die();
    }

    if (!isset($input['address_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No address_id is supplied");
        echo json_encode($errorData);

        die();
    }
    if (empty($input['address_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No address_id is supplied");
        echo json_encode($errorData);

        die();
    }


    $user_id = (int) $input['user_id'];
    $address_id = (int) $input['address_id'];

    //CHECK FOR BLOCKED CUSTOMER
    $checkk = mysqli_query($conn, "SELECT * FROM user WHERE id='$user_id' AND account_status IN('BLOCKED','INACTIVE')  ");
    if (mysqli_num_rows($checkk) > 0) {
        $errorData["data"] = array("status" => 0,   "message" => "This customer Account is Blocked or Inactive");
        echo json_encode($errorData);
        die();
    }
    //END oF BLOCKED
    validateUserId($conn, $user_id);
    validateUserIdAddressId($conn, $user_id, $address_id);

    $check = mysqli_query($conn, "DELETE FROM user_address WHERE id = $address_id AND user_id = $user_id");


    $checkk = mysqli_query($conn, "SELECT * FROM user WHERE id='$user_id'");
    $abc = mysqli_fetch_assoc($checkk);
    $user_address_id = (int) $abc['user_address_id'];
    $checkaddress = mysqli_query($conn, "SELECT * FROM user_address WHERE user_id = $user_id");
    if (mysqli_num_rows($checkaddress) > 0) {
        while ($rw = mysqli_fetch_assoc($checkaddress)) {
            if ((int) $rw['id'] == $user_address_id) {
                $address[] = array(
                    "user_address_id" => (int) $rw['id'],
                    "selected" => "YES",
                    'location' => $rw['location'],
                    'address' => $rw['address'],
                    'latitude' => $rw['latitude'],
                    'longitude' => $rw['longitude'],
                    'zone_id' => (int) $rw['zone_id']
                );
            } else {
                $address[] = array(
                    "user_address_id" => (int) $rw['id'],
                    "selected" => "NO",
                    'location' => $rw['location'],
                    'address' => $rw['address'],
                    'latitude' => $rw['latitude'],
                    'longitude' => $rw['longitude'],
                    'zone_id' => (int) $rw['zone_id']
                );
            }
        }
        if ($check) {
            $data = array("data" => array("status" => 1, "message" => "address_id deleted successfully", "address" => $address));
            echo json_encode($data);
            die(mysqli_error($conn));
        } else {
            $errorData["data"] = array("status" => 0,   "message" => "Internal server error");
            echo json_encode($errorData);
            die();
        }
    }
    
    else {
        $errorData["data"] = array("status" => 2,   "message" => "address_id deleted successfully", "address" => array());
        echo json_encode($errorData);
        die();
    }
}
function validateUserIdAddressId($conn, $user_id, $address_id)
{
    // echo "SELECT id FROM user_address WHERE id = $address_id AND user_id = $user_id";
    $check = mysqli_query($conn, "SELECT id FROM user_address WHERE id = $address_id AND user_id = $user_id");
    if (mysqli_num_rows($check) == 0) {
        $errorData["data"] = array("status" => 0,   "message" => "address_id and user_id mismatch");
        echo json_encode($errorData);
        die();
    } else {
        return true;
    }
}

function customerApplyWallet()
{
    $conn = $GLOBALS['conn'];
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['user_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No user_id is supplied");
        echo json_encode($errorData);

        die();
    }
    if (empty($input['user_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No user_id is supplied");
        echo json_encode($errorData);

        die();
    }

    if (!isset($input['booking_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No booking_id is supplied");
        echo json_encode($errorData);

        die();
    }
    if (empty($input['booking_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No booking_id is supplied");
        echo json_encode($errorData);

        die();
    }

    if (!isset($input['use_wallet'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No use_wallet is supplied");
        echo json_encode($errorData);

        die();
    }
    if (empty($input['use_wallet'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No use_wallet is supplied");
        echo json_encode($errorData);

        die();
    }


    $user_id = (int) $input['user_id'];
    $booking_id = (int) $input['booking_id'];
    $use_wallet =  $input['use_wallet'];


    //CHECK FOR BLOCKED CUSTOMER
    $checkk = mysqli_query($conn, "SELECT * FROM user WHERE id='$user_id' AND account_status IN('BLOCKED','INACTIVE')  ");
    if (mysqli_num_rows($checkk) > 0) {
        $errorData["data"] = array("status" => 0,   "message" => "This customer Account is Blocked or Inactive");
        echo json_encode($errorData);
        die();
    }
    //END oF BLOCKED
    validateUserId($conn, $user_id);

    validateuserIdBookingId($conn, $user_id, $booking_id);

    $check = mysqli_query($conn, "SELECT wallet_reduce, amount_payable FROM booking WHERE id = $booking_id AND user_id = $user_id AND payment IN ('PENDING')");
    $a = mysqli_fetch_assoc($check);
    $wallet_reduce = (int) $a['wallet_reduce'];
    $amount_payable = (int) $a['amount_payable'];

    if ($use_wallet == 'YES') {
        if ($wallet_reduce > 0) {
            $errorData["data"] = array("status" => 0,   "message" => "Wallet balance already applied");
            echo json_encode($errorData);
            die();
        }

        $wallet_balance = customerCheckWalletAvailable($conn, $user_id);

        if ($wallet_balance <= 0) {
            $errorData["data"] = array("status" => 0,   "message" => "Wallet balance is 0");
            echo json_encode($errorData);
            die();
        }

        if ($amount_payable <= $wallet_balance) {
            $wallet_balance_used = $wallet_balance - $amount_payable;
        } else if ($amount_payable > $wallet_balance) {
            $wallet_balance_used = $wallet_balance;
        }
        $amount_payable_updated = $amount_payable - $wallet_balance_used;

        $q1 = mysqli_query($conn, "INSERT INTO user_wallet(user_id, amount, booking_id, type, description)
                                    VALUES($user_id, $wallet_balance_used, $booking_id, 'debit', 'Debited for booking ID $booking_id')");
        $q2 = mysqli_query($conn, "UPDATE booking SET wallet_reduce = $wallet_balance_used, amount_payable = $amount_payable_updated WHERE id = $booking_id AND user_id = $user_id");

        if ($q1 && $q2) {
            $errorData["data"] = array("status" => 1,   "message" => "Wallet balance applied successfully");
            echo json_encode($errorData);
            die();
        } else {
            $errorData["data"] = array("status" => 0,   "message" => "internal server error");
            echo json_encode($errorData);
            die();
        }
    } else if ($use_wallet == 'NO') {
        if ($wallet_reduce == 0) {
            $errorData["data"] = array("status" => 0,   "message" => "Wallet balance already removed");
            echo json_encode($errorData);
            die();
        }
        $zz = mysqli_query($conn, "SELECT id,amount FROM user_wallet WHERE booking_id = $booking_id AND user_id = $user_id  ");
        $yy = mysqli_fetch_assoc($zz);
        $deleteId = (int) $yy['id'];
        $amount = (int) $yy['amount'];

        $amount_payable_updated = $amount_payable + $amount;
        $q1 = mysqli_query($conn, "DELETE FROM user_wallet WHERE id = $deleteId AND booking_id = $booking_id AND user_id = $user_id");
        $q2 = mysqli_query($conn, "UPDATE booking SET wallet_reduce = 0, amount_payable = $amount_payable_updated WHERE id = $booking_id AND user_id = $user_id");

        if ($q1 && $q2) {
            $errorData["data"] = array("status" => 1,   "message" => "Wallet balance removed successfully");
            echo json_encode($errorData);
            die();
        } else {
            $errorData["data"] = array("status" => 0,   "message" => "internal server error");
            echo json_encode($errorData);
            die();
        }
    }
}

function validateuserIdBookingId($conn, $user_id, $booking_id)
{
    $check = mysqli_query($conn, "SELECT id FROM booking WHERE id = $booking_id AND user_id = $user_id");
    if (mysqli_num_rows($check) == 0) {
        $errorData["data"] = array("status" => 0,   "message" => "booking_id and user_id mismatch");
        echo json_encode($errorData);
        die();
    } else {
        return true;
    }
}

function customerCheckWalletAvailable($conn, $user_id)
{
    $credits = mysqli_query($conn, "SELECT SUM(amount) AS credits FROM user_wallet WHERE user_id=$user_id  AND type='credit'");
    $a = mysqli_fetch_assoc($credits);
    $total_credits = (int) $a['credits'];
    $debits = mysqli_query($conn, "SELECT SUM(amount) AS debits FROM user_wallet WHERE user_id=$user_id  AND type='debit'");
    $a = mysqli_fetch_assoc($debits);
    $total_debits = (int) $a['debits'];

    $available_balance = $total_credits - $total_debits;

    if ($available_balance <= 0) {
        return 0;
    } else {
        return $available_balance;
    }
}

function customerViewCoupons()
{
    $conn = $GLOBALS['conn'];
    $input = json_decode(file_get_contents('php://input'), true);

    date_default_timezone_set('Asia/Calcutta');
    $today_date =  date('Y-m-d');

    $q = mysqli_query($conn, "SELECT * FROM `coupon` WHERE expiry>'$today_date'");
    if (mysqli_num_rows($q) == 0) {
        $errorData["data"] = array("status" => 0,   "message" => "No coupons available");
        echo json_encode($errorData);
        die();
    }

    while ($row = mysqli_fetch_assoc($q)) {
        $coupon[] = array("code" => $row['code'], "description" => $row['description']);
    }
    $response["data"] = array("status" => 1,   "message" => "Coupons available", "coupons" => $coupon);
    echo json_encode($response);
    die();
}

function customerApplyCoupon()
{
    $conn = $GLOBALS['conn'];
    $input = json_decode(file_get_contents('php://input'), true);

    date_default_timezone_set('Asia/Calcutta');
    $today_date =  date('Y-m-d');

    if (!isset($input['user_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No user_id is supplied");
        echo json_encode($errorData);

        die();
    }
    if (empty($input['user_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No user_id is supplied");
        echo json_encode($errorData);

        die();
    }

    if (!isset($input['booking_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No booking_id is supplied");
        echo json_encode($errorData);

        die();
    }
    if (empty($input['booking_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No booking_id is supplied");
        echo json_encode($errorData);

        die();
    }

    if (!isset($input['coupon'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No coupon is supplied");
        echo json_encode($errorData);

        die();
    }
    if (empty($input['coupon'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No coupon is supplied");
        echo json_encode($errorData);

        die();
    }


    $user_id = (int) $input['user_id'];
    $booking_id = (int) $input['booking_id'];
    $coupon =  $input['coupon'];


    //CHECK FOR BLOCKED CUSTOMER
    $checkk = mysqli_query($conn, "SELECT * FROM user WHERE id='$user_id' AND account_status IN('BLOCKED','INACTIVE')  ");
    if (mysqli_num_rows($checkk) > 0) {
        $errorData["data"] = array("status" => 0,   "message" => "This customer Account is Blocked or Inactive");
        echo json_encode($errorData);
        die();
    }
    //END oF BLOCKED
    validateUserId($conn, $user_id);

    validateuserIdBookingId($conn, $user_id, $booking_id);

    $check = mysqli_query($conn, "SELECT * FROM coupon WHERE code = '$coupon' AND expiry>'$today_date' ");
    if (mysqli_num_rows($check) == 0) {
        $errorData["data"] = array("status" => 0,   "message" => "Coupon is invalid or expired");
        echo json_encode($errorData);
        die();
    }

    $coupon_details = mysqli_fetch_assoc($check);
    $coupon_code = $coupon_details['code'];
    $coupon_amount = (int) $coupon_details['amount'];

    $abc = mysqli_query($conn, "SELECT * FROM booking WHERE id = $booking_id AND user_id='$user_id' AND servicer_status ='Completed' AND payment ='PENDING' ");
    if (mysqli_num_rows($abc) == 0) {
        $errorData["data"] = array("status" => 0,   "message" => "coupon cannot be applied to this booking_id");
        echo json_encode($errorData);
        die();
    }
    $xy = mysqli_fetch_assoc($abc);
    $coupon_reduce = (int) $xy['coupon_reduce'];
    if ($coupon_reduce > 0) {
        $errorData["data"] = array("status" => 0,   "message" => "Already some other coupon is applied");
        echo json_encode($errorData);
        die();
    }

    $amount_payable = (int) $xy['amount_payable'];
    $amount_payable_updated = $amount_payable - $coupon_amount;

    $q1 = mysqli_query($conn, "UPDATE booking SET coupon_reduce = $coupon_amount, coupon = '$coupon_code', amount_payable = $amount_payable_updated WHERE id = $booking_id AND user_id='$user_id' ");
    if ($q1) {
        $response["data"] = array("status" => 1,   "message" => "Coupon applied successfully");
        echo json_encode($response);
        die();
    } else {
        $errorData["data"] = array("status" => 0,   "message" => "internal server error");
        echo json_encode($errorData);
        die();
    }
}

function customerRemoveCoupon()
{
    $conn = $GLOBALS['conn'];
    $input = json_decode(file_get_contents('php://input'), true);

    date_default_timezone_set('Asia/Calcutta');
    $today_date =  date('Y-m-d');

    if (!isset($input['user_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No user_id is supplied");
        echo json_encode($errorData);

        die();
    }
    if (empty($input['user_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No user_id is supplied");
        echo json_encode($errorData);

        die();
    }

    if (!isset($input['booking_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No booking_id is supplied");
        echo json_encode($errorData);

        die();
    }
    if (empty($input['booking_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No booking_id is supplied");
        echo json_encode($errorData);

        die();
    }


    $user_id = (int) $input['user_id'];
    $booking_id = (int) $input['booking_id'];


    //CHECK FOR BLOCKED CUSTOMER
    $checkk = mysqli_query($conn, "SELECT * FROM user WHERE id='$user_id' AND account_status IN('BLOCKED','INACTIVE')  ");
    if (mysqli_num_rows($checkk) > 0) {
        $errorData["data"] = array("status" => 0,   "message" => "This customer Account is Blocked or Inactive");
        echo json_encode($errorData);
        die();
    }
    //END oF BLOCKED
    validateUserId($conn, $user_id);

    validateuserIdBookingId($conn, $user_id, $booking_id);

    $abc = mysqli_query($conn, "SELECT * FROM booking WHERE id = $booking_id AND user_id='$user_id' AND servicer_status ='Completed' AND payment ='PENDING' ");
    if (mysqli_num_rows($abc) == 0) {
        $errorData["data"] = array("status" => 0,   "message" => "coupon cannot be applied to this booking_id");
        echo json_encode($errorData);
        die();
    }
    $xy = mysqli_fetch_assoc($abc);
    $coupon_reduce = (int) $xy['coupon_reduce'];
    if ($coupon_reduce == 0) {
        $errorData["data"] = array("status" => 0,   "message" => "Already coupon is removed");
        echo json_encode($errorData);
        die();
    }

    $amount_payable = (int) $xy['amount_payable'];
    $coupon_reduce = (int) $xy['coupon_reduce'];

    $amount_payable_updated = $amount_payable + $coupon_reduce;

    $q1 = mysqli_query($conn, "UPDATE booking SET coupon_reduce = 0, coupon = NULL, amount_payable = $amount_payable_updated WHERE id = $booking_id AND user_id='$user_id' ");
    if ($q1) {
        $response["data"] = array("status" => 1,   "message" => "Coupon removed successfully");
        echo json_encode($response);
        die();
    } else {
        $errorData["data"] = array("status" => 0,   "message" => "internal server error");
        echo json_encode($errorData);
        die();
    }
}

function customerViewWallet()
{
    $conn = $GLOBALS['conn'];
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($_GET['user_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No user_id is supplied");
        echo json_encode($errorData);

        die();
    }
    if (empty($_GET['user_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No user_id is supplied");
        echo json_encode($errorData);

        die();
    }
    $user_id = (int) $_GET['user_id'];
    //CHECK FOR BLOCKED CUSTOMER
    $checkk = mysqli_query($conn, "SELECT * FROM user WHERE id='$user_id' AND account_status IN('BLOCKED','INACTIVE')  ");
    if (mysqli_num_rows($checkk) > 0) {
        $errorData["data"] = array("status" => 0,   "message" => "This customer Account is Blocked or Inactive");
        echo json_encode($errorData);
        die();
    }
    //END oF BLOCKED
    validateUserId($conn, $user_id);
    $wallet_balance = customerCheckWalletAvailable($conn, $user_id);

    $response["data"] = array("status" => 1,   "message" => "Wallet balance for the user : $user_id", "wallet_balance" => $wallet_balance);
    echo json_encode($response);
    die();
}

function clearCart()
{
    $conn = $GLOBALS['conn'];
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['user_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No user_id is supplied");
        echo json_encode($errorData);

        die();
    }
    if (empty($input['user_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No user_id is supplied");
        echo json_encode($errorData);

        die();
    }
    $user_id = (int) $input['user_id'];
    //CHECK FOR BLOCKED CUSTOMER
    $checkk = mysqli_query($conn, "SELECT * FROM user WHERE id='$user_id' AND account_status IN('BLOCKED','INACTIVE')  ");
    if (mysqli_num_rows($checkk) > 0) {
        $errorData["data"] = array("status" => 0,   "message" => "This customer Account is Blocked or Inactive");
        echo json_encode($errorData);
        die();
    }
    //END oF BLOCKED
    validateUserId($conn, $user_id);

    if (isset($input['type'])) {
        $type = $input['type'];
        if (empty($input['type'])) {
            $errorData["data"] = array("status" => 0,   "message" => "No type is supplied");
            echo json_encode($errorData);
            die();
        }
        if ($type == '1') {
            ////////////////////////////////////clear cart and add type 1////////////////////////////
            ////////////////////clear cart/////////////////////////////
            $q1 = mysqli_query($conn, "DELETE FROM cart WHERE user_id = $user_id");
            ///////////////////add type 1///////////////////////////////////////////
            ////////////////////////////////////////////////////////////////HOME TYPE 1//////////////////////////////////////////////////////////////
            if (!isset($input['product_id'])) {
                $errorData["data"] = array("status" => 0,   "message" => "No product_id is supplied");
                echo json_encode($errorData);
                die();
            }
            if (empty($input['product_id'])) {
                $errorData["data"] = array("status" => 0,   "message" => "No product_id is supplied");
                echo json_encode($errorData);

                die();
            }
            $product_id = (int) $input['product_id'];

            $checkname = mysqli_query($conn, "SELECT product_title,price, product_description from products WHERE id = $product_id");
            $rrr = mysqli_fetch_assoc($checkname);
            $product_price = (int) $rrr['price'];
            $product_title = $rrr['product_title'];
            $product_description = $rrr['product_description'];

            $checkproductname = mysqli_query($conn, "SELECT mc.name FROM main_categories mc INNER JOIN sub_categories sc ON sc.main_category_id=mc.id
                                                        INNER JOIN products p on p.category_id = sc.id AND p.id=$product_id ");
            $rw = mysqli_fetch_assoc($checkproductname);
            $mainCategoryName = $rw['name'];

            $updated = date('Y-m-d');

            $priceCheck = mysqli_query($conn, "SELECT price from products WHERE id = $product_id");
            $rw = mysqli_fetch_assoc($priceCheck);
            $price = (int) $rw['price'];

            $res1 = mysqli_query($conn, "INSERT INTO cart(home_category_id, user_id, estimate) 
                                            VALUES ($type, $user_id, $price )");
            $cart_id = (int) $conn->insert_id;

            $res = mysqli_query($conn, "INSERT INTO cart_item(cart_id, product_id, count,price) 
                                            VALUES ( $cart_id, $product_id, 1, $price)");
            $cart_item_id =  (int) $conn->insert_id;
            if ($res and $res1) {
                $cartitemcheck = mysqli_query($conn, "SELECT * from cart_item WHERE cart_id=$cart_id ");
                while ($rw = mysqli_fetch_assoc($cartitemcheck)) {
                    $cartitems[] = array(
                        "cartItemId" => (int) $rw['id'],
                        "productId" => (int) $rw['product_id'],
                        "productTitle" => $product_title,
                        "productDescription" => $product_description,
                        "mainCategoryName" => $mainCategoryName,
                        "count" => (int) $rw['count'],
                        "price" => (int) $rw['price']
                    );
                }

                $cartcheck = mysqli_query($conn, "SELECT * from cart WHERE id=$cart_id ");
                while ($rw = mysqli_fetch_assoc($cartcheck)) {
                    $response["data"] = array(
                        "status" => 1,
                        "message" => "Item added to cart successfully",
                        "type" => $type,
                        "user_id" => $user_id,
                        "cartId" => (int) $rw['id'],
                        "estimate" => (int) $rw['estimate'],
                        "user_address_id" => (int) $rw['user_address_id'],
                        "date" => $rw['date'],
                        "slot_id" => (int) $rw['slot_id'],
                        "cartItems" => $cartitems
                    );
                    echo json_encode($response);
                    die(mysqli_error($conn));
                }
            }
            //
            else {
                $errorData["data"] = array("status" => 0, "code" => 500, "message" => "Something went wrong in serverr");
                echo json_encode($errorData);

                die(mysqli_error($conn));
            }
            //////////////////end of add type 1
        } else if ($type == '2') {
            ////////////////////////////////////////////////////////////////HOME TYPE 2//////////////////////////////////////////////////////////////
            ////////////////////clear cart/////////////////////////////
            $q1 = mysqli_query($conn, "DELETE FROM cart WHERE user_id = $user_id");

            if (!isset($input['tariff_id'])) {
                $errorData["data"] = array("status" => 0,   "message" => "No tariff_id is supplied");
                echo json_encode($errorData);
                die();
            }
            $tariff_id = (int) $input['tariff_id'];

            $test = mysqli_query($conn, "SELECT mc.home_category_id FROM main_categories mc
                                                                INNER JOIN rate_visits rv ON rv.main_category_id=mc.id
                                                                WHERE rv.id = $tariff_id");
            $row = mysqli_fetch_assoc($test);
            $home_category_id = (int) $row['home_category_id'];
            if ($home_category_id != $type) {
                $errorData["data"] = array("status" => 0,   "message" => "This tariff_id is not from type 2");
                echo json_encode($errorData);
                die();
            }

            if (!isset($input['quantity'])) {
                $errorData["data"] = array("status" => 0,   "message" => "No quantity is supplied");
                echo json_encode($errorData);
                die();
            }
            $quantity = (int) $input['quantity'];
            if ($quantity < 1) {
                $errorData["data"] = array("status" => 0,   "message" => "quantity is less than 1");
                echo json_encode($errorData);
                die();
            }
            /////////////////////////////////////////////cart is free for this user///////////////////////////////////////////////////////
            $priceCheck = mysqli_query($conn, "SELECT rate,visits,main_category_id from rate_visits WHERE id = $tariff_id");
            if (mysqli_num_rows($priceCheck) == 0) {
                $errorData["data"] = array("status" => 0,   "message" => "No tariff available for this details");
                echo json_encode($errorData);
                die();
            }
            $rw = mysqli_fetch_assoc($priceCheck);
            $visits = (int) $rw['visits'];
            $main_category_id = (int) $rw['main_category_id'];


            $ratePerQuantity = (int) $rw['rate'];
            $estimate = $ratePerQuantity * $quantity;

            $res1 = mysqli_query($conn, "INSERT INTO cart(home_category_id, user_id, estimate) 
                                            VALUES ($type, $user_id, $estimate )");
            $cart_id = (int) $conn->insert_id;

            $res = mysqli_query($conn, "INSERT INTO cart_item(cart_id, main_category_id, count, price,visits) 
                                            VALUES ( $cart_id, $main_category_id, $quantity, $estimate, $visits)");
            $cart_item_id =  (int) $conn->insert_id;
            if ($res and $res1) {
                $response["data"] = array(
                    "status" => 1,
                    "message" => "Type 2A Item added to cart successfully",
                    "type" => $type
                );
                echo json_encode($response);
                die(mysqli_error($conn));
            }
            //
            else {
                $errorData["data"] = array("status" => 0, "code" => 500, "message" => "Something went wrong in server");
                echo json_encode($errorData);

                die(mysqli_error($conn));
            }
        } else if ($type == '2B') {
            ////////////////////////////////////////////////////////////////HOME TYPE 2B//////////////////////////////////////////////////////////////
            ////////////////////clear cart/////////////////////////////
            $q1 = mysqli_query($conn, "DELETE FROM cart WHERE user_id = $user_id");

            if (!isset($input['tariff_id'])) {
                $errorData["data"] = array("status" => 0,   "message" => "No tariff_id is supplied");
                echo json_encode($errorData);
                die();
            }
            if (empty($input['tariff_id'])) {
                $errorData["data"] = array("status" => 0,   "message" => "No tariff_id is supplied");
                echo json_encode($errorData);
                die();
            }
            $tariff_id = (int) $input['tariff_id'];

            if (!isset($input['quantity'])) {
                $errorData["data"] = array("status" => 0,   "message" => "No quantity is supplied");
                echo json_encode($errorData);
                die();
            }
            if (empty($input['quantity'])) {
                $errorData["data"] = array("status" => 0,   "message" => "No quantity is supplied");
                echo json_encode($errorData);
                die();
            }
            $quantity = (int) $input['quantity'];

            //cart is free for this user///////////////////////////////////////////////////////
            $priceCheck = mysqli_query($conn, "SELECT rate,visits,main_category_id from rate_visits WHERE id = $tariff_id");
            if (mysqli_num_rows($priceCheck) == 0) {
                $errorData["data"] = array("status" => 0,   "message" => "No tariff available for this details");
                echo json_encode($errorData);
                die();
            }
            $rw = mysqli_fetch_assoc($priceCheck);
            $visits = (int) $rw['visits'];
            $main_category_id = (int) $rw['main_category_id'];


            $ratePerQuantity = (int) $rw['rate'];
            // echo $ratePerQuantity."    ".$quantity;
            $estimate = $ratePerQuantity * $quantity;



            $home_category_id = substr($type, 0, 1);

            $res1 = mysqli_query($conn, "INSERT INTO cart(home_category_id, user_id, estimate) 
                                                        VALUES ($home_category_id, $user_id, $estimate )");
            $cart_id = (int) $conn->insert_id;

            $res = mysqli_query($conn, "INSERT INTO cart_item(cart_id, main_category_id, count, price,visits) 
                                                        VALUES ( $cart_id, $main_category_id, $quantity, $estimate, $visits)");
            $cart_item_id =  (int) $conn->insert_id;
            if ($res and $res1) {
                $response["data"] = array(
                    "status" => 1,
                    "message" => "Type 2B Item added to cart successfully",
                    "type" => $type
                );
                echo json_encode($response);
                die(mysqli_error($conn));
            }
            //
            else {
                $errorData["data"] = array("status" => 0, "code" => 500, "message" => "Something went wrong in server");
                echo json_encode($errorData);

                die(mysqli_error($conn));
            }
        }
    }

    $q1 = mysqli_query($conn, "DELETE FROM cart WHERE user_id = $user_id");
    if ($q1) {
        $response["data"] = array("status" => 1,   "message" => "All items in the cart deleted successfully for this user.");
        echo json_encode($response);
        die();
    } else {
        $errorData["data"] = array("status" => 0,   "message" => "internal server error");
        echo json_encode($errorData);
        die();
    }
}

// 35 : Help Topics and answers
function helpCenter()
{
    $conn = $GLOBALS['conn'];
    $input = json_decode(file_get_contents('php://input'), true);
    $cat_id_index_map = [];
    $main_categories = [];


    $sub_topics = mysqli_query($conn, "SELECT 
        sub.id AS sub_cat_id, sub.sub_title AS sub_topic, sub.description AS description,
        main.id AS main_cat_id, main.title AS name
        FROM help_center_qa AS sub 
        INNER JOIN help_center_title AS main
        ON sub.help_id = main.id
        WHERE main.display = 'YES' AND sub.display = 'YES'");

    while ($row = mysqli_fetch_assoc($sub_topics)) {
        $main_cat_id = $row["main_cat_id"];
        $name = $row["name"];

        $sub_category = array(
            "sub_cat_id" => $row["sub_cat_id"],
            "sub_topic" => $row["sub_topic"],
            "description" => $row["description"]
        );

        if (isset($cat_id_index_map[$main_cat_id])) {
            $main_categories[$cat_id_index_map[$main_cat_id]]["sub_categories"][] = $sub_category;
        } else {
            $main_categories[] = array(
                "main_cat_id" => $main_cat_id,
                "name" => $name,
                "sub_categories" => array($sub_category)
            );
        }
    }

    $response["data"] = array("status" => 1, "message" => "Help topics found", "main_categories" => $main_categories);
    echo json_encode($response);
    die();
}

// 18 : View Cart
function viewCart()
{
    $conn = $GLOBALS['conn'];
    $user_id = $_GET["user_id"];

    $cart = mysqli_query($conn, "SELECT 
        cart.user_id AS user_id,
        cart.id AS cartId,
        cart.estimate AS estimate,
        cart.user_address_id AS user_address_id,
        cart.date AS date,
        cart.slot_id AS slot_id,
        cart.home_category_id AS home_category_id
        FROM cart 
        WHERE cart.user_id = $user_id");
    if ($cart_row = mysqli_fetch_assoc($cart)) {
        $home_category_id = (int) $cart_row["home_category_id"];
        $cart_id = (int) $cart_row["cartId"];

        if ($home_category_id == 1) {
            $cart_items = [];
            $cart_items_result = mysqli_query($conn, "SELECT 
                cart_item.id AS cartItemId,
                cart_item.product_id AS productId,
                products.product_title AS productTitle,
                products.product_description AS productDescription,
                main_categories.name AS mainCategoryName,
                cart_item.count AS count,
                cart_item.price AS price
                FROM cart_item
                INNER JOIN products
                ON cart_item.product_id = products.id
                LEFT JOIN main_categories
                ON cart_item.main_category_id = main_categories.id
                WHERE cart_item.cart_id = $cart_id");

            while ($cart_item_row = mysqli_fetch_assoc($cart_items_result)) {
                $cart_items[] = $cart_item_row;
            }

            $errorData["data"] = array(
                "status" => 1,
                "message" => "cart for the user",
                "user_id" => (int) $user_id,
                "cartId" => (int) $cart_row["cartId"],
                "estimate" => (int) $cart_row["estimate"],
                "user_address_id" => (int) $cart_row["user_address_id"],
                "date" => $cart_row["date"],
                "slot_id" => (int) $cart_row["slot_id"],
                "cartItems" => $cart_items
            );
            echo json_encode($errorData);
            die();
        } else {
            $details = mysqli_query($conn, "SELECT
            mc.name AS main_category_name,
            cart_item.main_category_id AS main_category_id,
            cart_item.price AS estimate,
            cart_item.count AS count,
            hc.tax_percent AS tax_percent,
            ROUND(hc.tax_percent * cart_item.price / 100) AS tax_amount,
            ( cart_item.price+ROUND(hc.tax_percent * cart_item.price / 100)) AS total,
            CONCAT(mc.name,' (For ',rv.visits,' ', IF(mc.division = 'A', 'Services', 'Visits'),' per year)') AS description,
            rv.visits AS visits
            FROM cart_item
            INNER JOIN main_categories mc
            ON cart_item.main_category_id = mc.id
            INNER JOIN rate_visits rv
            ON mc.id = rv.main_category_id
            INNER JOIN home_categories hc
            ON hc.id = mc.home_category_id
            WHERE cart_item.cart_id = $cart_id 
            ORDER BY rv.visits ASC
            LIMIT 1");
            if ($details_row = mysqli_fetch_assoc($details)) {
                $details_row["status"] = 1;
                $details_row["message"] = "cart for the user";
                $details_row["type"] = 2;
                $details_row["user_id"] = (int) $user_id;
                $details_row["cartId"] = (int) $cart_id;
                $details_row["main_category_id_chosen"] = 0;
                echo json_encode(array("data" => $details_row));
                die();
            }
        }
    } else {
        $errorData["data"] = array("status" => 0,   "message" => "Cart is empty");
        echo json_encode($errorData);
        die();
    }
}

function getHelp()
{
    $conn = $GLOBALS['conn'];
    $mc_id = (int) $_GET['mc_id'];
    $q = mysqli_query($conn, "SELECT home_category_id, division, how, about, image, name FROM `main_categories` WHERE id = $mc_id LIMIT 1 ");
    if (mysqli_num_rows($q) > 0) {
        $row = mysqli_fetch_assoc($q);
        $rate = $row['rate'];
        $how = $row['how'];
        $about = $row['about'];
        $image = $row['image'];

        $home_category_id = (int) $row['home_category_id'];
        $division = $row['division'];
        $name = $row['name'];

        if ($home_category_id == 2 & $division == 'A') {
            $q1 = mysqli_query($conn, "SELECT rate, visits FROM rate_visits WHERE main_category_id=$mc_id ORDER BY rate ASC LIMIT 1");
            $aa = mysqli_fetch_assoc($q1);
            $rate = (int) $aa['rate'];
            $visits = (int) $aa['visits'];
            $description = "Rs. " . $rate . " per year / " . $visits . " Services";
        } else if ($home_category_id == 2 & $division == 'B') {
            $q1 = mysqli_query($conn, "SELECT rate, visits, minutes_per_visit FROM rate_visits WHERE main_category_id=$mc_id ORDER BY rate ASC LIMIT 1");

            $aa = mysqli_fetch_assoc($q1);
            $rate = (int) $aa['rate'];
            $visits = (int) $aa['visits'];
            $minutes_per_visit = (int) $aa['minutes_per_visit'];
            $hours = ($visits * $minutes_per_visit) / 60;
            $description = "Rs. " . $rate . " per year / " . $visits . "Visits / " . $hours . " hours";
        } else {
            $description = '';
        }



        $response["data"] = array("status" => 1, "message" => "Help", "how" => $how, "about" => $about, "image" => $image, "name" => $name, "description" => $description);
        echo json_encode($response);
        die();
    } else {
        $errorData["data"] = array("status" => 0, "message" => "Category not found", "error" => mysqli_error($conn));
        echo json_encode($errorData);
        die();
    }
}

function customerConfirmAddress()
{
    $conn = $GLOBALS['conn'];

    $input = json_decode(file_get_contents('php://input'), true);


    if (!isset($input['user_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No user_id is supplied");
        echo json_encode($errorData);

        die();
    }
    if (empty($input['user_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No user_id is supplied");
        echo json_encode($errorData);

        die();
    }

    if (!isset($input['user_address_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No user_address_id is supplied");
        echo json_encode($errorData);

        die();
    }
    if (empty($input['user_address_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No user_address_id is supplied");
        echo json_encode($errorData);

        die();
    }
    $user_id = (int) $input['user_id'];
    //CHECK FOR BLOCKED CUSTOMER
    $checkk = mysqli_query($conn, "SELECT * FROM user WHERE id='$user_id' AND account_status IN('BLOCKED','INACTIVE')  ");
    if (mysqli_num_rows($checkk) > 0) {
        $errorData["data"] = array("status" => 0,   "message" => "This customer Account is Blocked or Inactive");
        echo json_encode($errorData);
        die();
    }
    //END oF BLOCKED


    $user_address_id = (int) $input['user_address_id'];
    $user_id = (int) $input['user_id'];

    validateUserId($conn, $user_id);

    $addresscheck = mysqli_query($conn, "SELECT * from user_address WHERE id =$user_address_id ");
    if (mysqli_num_rows($addresscheck)) {
        $abc = mysqli_fetch_assoc($addresscheck);
        $zone_id = (int) $abc['zone_id'];
        $q1 = mysqli_query($conn, "SELECT * from cart WHERE user_id = $user_id");
        if (mysqli_num_rows($q1)) {
            $q2 = mysqli_query($conn, "UPDATE cart SET user_address_id =$user_address_id WHERE user_id= $user_id  ");
            if ($q2) {
                $rrr = mysqli_fetch_assoc($q1);
                $cart_id = (int) $rrr['id'];
                $cartitemcheck = mysqli_query($conn, "SELECT * from cart_item WHERE cart_id=$cart_id ");
                $cartitems = [];
                while ($rrow = mysqli_fetch_assoc($cartitemcheck)) {
                    $productId = (int) $rrow['product_id'];
                    $checkname = mysqli_query($conn, "SELECT product_title, product_description from products WHERE id = $productId");
                    $r = mysqli_fetch_assoc($checkname);
                    $product_title = $r['product_title'];
                    $product_description = $r['product_description'];

                    $checkproductname = mysqli_query($conn, "SELECT mc.name FROM main_categories mc INNER JOIN sub_categories sc ON sc.main_category_id=mc.id
                                                                INNER JOIN products p on p.category_id = sc.id AND p.id=$productId ");
                    $rw = mysqli_fetch_assoc($checkproductname);
                    $mainCategoryName = $rw['name'];

                    $cartitems[] = array(
                        "cartItemId" => (int) $rrow['id'],
                        "productId" => (int) $rrow['product_id'],
                        "productTitle" => $product_title,
                        "productDescription" => $product_description,
                        "mainCategoryName" => $mainCategoryName,
                        "count" => (int) $rrow['count'],
                        "price" => (int) $rrow['price']
                    );
                }

                $cartcheck = mysqli_query($conn, "SELECT * from cart WHERE id=$cart_id ");
                $cartrow = mysqli_fetch_assoc($cartcheck);
                $response["data"] = array(
                    "status" => 1,
                    "message" => "Address confirmed and added to cart",
                    "zone_id" => $zone_id,
                    "user_id" => $user_id,
                    "cartId" => (int) $cartrow['id'],
                    "estimate" => (int) $cartrow['estimate'],
                    "user_address_id" => (int) $cartrow['user_address_id'],
                    "date" => $cartrow['date'],
                    "slot_id" => (int) $cartrow['slot_id'],
                    "cartItems" => $cartitems
                );
                echo json_encode($response);
                die(mysqli_error($conn));
            }
            //
            else {
                $errorData["error"] = array("status" => 0, "message" => "something went wrong in server");
                echo json_encode($errorData);

                die(mysqli_error($conn));
            }
        }
        //
        else {
            $q1 = mysqli_query($conn, "UPDATE user SET user_address_id = $user_address_id WHERE id = $user_id");
            if ($q1) {
                $errorData["data"] = array("status" => 1, "message" => "The selected address is stored for this user", "zone_id" => $zone_id);
                echo json_encode($errorData);
                die(mysqli_error($conn));
            } else {
                $errorData["error"] = array("status" => 0, "message" => "internal server error");
                echo json_encode($errorData);
                die(mysqli_error($conn));
            }
        }
    }
    //
    else {
        $errorData["error"] = array("status" => 0, "message" => "No such address available for this user.");
        echo json_encode($errorData);
        die(mysqli_error($conn));
    }
}



function cartSummary()
{
    $conn = $GLOBALS['conn'];

    $user_id = (int) $_GET['user_id'];

    $checkcart = mysqli_query($conn, "SELECT * FROM cart WHERE user_id = $user_id");
    if (mysqli_num_rows($checkcart) > 0) {
        $cartidrow = mysqli_fetch_assoc($checkcart);
        $cart_id = (int) $cartidrow['id'];

        $cartitemcheck = mysqli_query($conn, "SELECT * from cart_item WHERE cart_id=$cart_id ");
        $cartitems = [];
        while ($rrow = mysqli_fetch_assoc($cartitemcheck)) {
            $productId = (int) $rrow['product_id'];
            $checkname = mysqli_query($conn, "SELECT product_title, product_description from products WHERE id = $productId");
            $r = mysqli_fetch_assoc($checkname);
            $product_title = $r['product_title'];
            $product_description = $r['product_description'];

            $checkproductname = mysqli_query($conn, "SELECT mc.name FROM main_categories mc INNER JOIN sub_categories sc ON sc.main_category_id=mc.id
                                                INNER JOIN products p on p.category_id = sc.id AND p.id=$productId ");
            $rw = mysqli_fetch_assoc($checkproductname);
            $mainCategoryName = $rw['name'];

            $cartitems[] = array(
                "cartItemId" => (int) $rrow['id'],
                "productId" => (int) $rrow['product_id'],
                "productTitle" => $product_title,
                "productDescription" => $product_description,
                "mainCategoryName" => $mainCategoryName,
                "count" => (int) $rrow['count'],
                "price" => (int) $rrow['price']
            );
        }

        $cartcheck = mysqli_query($conn, "SELECT * from cart WHERE id=$cart_id ");
        $cartrow = mysqli_fetch_assoc($cartcheck);

        //get slot name from slot_id
        $slot_id = (int) $cartrow['slot_id'];
        $slotnamecheck = mysqli_query($conn, "SELECT name from slot WHERE id = $slot_id");
        $abc = mysqli_fetch_assoc($slotnamecheck);
        $slot_name = $abc['name'];

        //get usert address from user_address_id
        $user_address_id = (int) $cartrow['user_address_id'];
        $addrcheck = mysqli_query($conn, "SELECT address from user_address WHERE id = $user_address_id");
        $abc = mysqli_fetch_assoc($addrcheck);
        $address = $abc['address'];

        $response["data"] = array(
            "status" => 1,
            "message" => "cart summary for the user",
            "user_id" => $user_id,
            "cartId" => (int) $cartrow['id'],
            "estimate" => (int) $cartrow['estimate'],
            "user_address_id" => (int) $cartrow['user_address_id'],
            "user_address" => $address,
            "date" => $cartrow['date'],
            "slot_id" => (int) $cartrow['slot_id'],
            "slot_name" => $slot_name,
            "cartItems" => $cartitems
        );
        echo json_encode($response);
        die(mysqli_error($conn));
    }
    //
    else {
        $errorData["data"] = array("status" => 0,"message" => "No items in the cart for this user.");
        echo json_encode($errorData);
        die(mysqli_error($conn));
    }
}


function type23Tariff()
{
    $conn = $GLOBALS['conn'];

    $input = json_decode(file_get_contents('php://input'), true);


    if (!isset($_GET['category_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No category_id is supplied");
        echo json_encode($errorData);

        die();
    }
    if (empty($_GET['category_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No category_id is supplied");
        echo json_encode($errorData);

        die();
    }
    $main_category_id = (int) $_GET['category_id'];
    $q = mysqli_query($conn, "SELECT * FROM rate_visits WHERE main_category_id = $main_category_id ORDER BY rate ASC");
    if (mysqli_num_rows($q) == 0) {
        $errorData["data"] = array("status" => 0,   "message" => "No Tariff found for this category");
        echo json_encode($errorData);
        die();
    }
    while ($row = mysqli_fetch_assoc($q)) {
        $minutes_per_visit = (int) $row['minutes_per_visit'];
        if ($minutes_per_visit == 0) {
            $hours = (((int) $row['visits']) * $minutes_per_visit) / 60;
            $tariff[] = array(
                "tariff_id" => (int) $row['id'],
                "rate" => (int) $row['rate'],
                "visits" => (int) $row['visits'],
                "hours" => ''
            );
        } else {
            $hours = (((int) $row['visits']) * $minutes_per_visit) / 60;
            $tariff[] = array(
                "tariff_id" => (int) $row['id'],
                "rate" => (int) $row['rate'],
                "visits" => (int) $row['visits'],
                "hours" => $hours
            );
        }
    }
    $response['data'] = array("status" => 1, "message" => "tariff for main category id: $main_category_id", "tariff" => $tariff);
    echo json_encode($response);
    die();
}


function customerChooseServiceList()
{
    $conn = $GLOBALS['conn'];
    $q = mysqli_query($conn, "SELECT id, icon, name FROM main_categories WHERE home_category_id = 1");
    if (mysqli_num_rows($q) == 0) {
        $errorData["data"] = array("status" => 0,   "message" => "No items found");
        echo json_encode($errorData);
        die();
    }
    while ($row = mysqli_fetch_assoc($q)) {
        $mainCat[] = array(
            "mainCategoryId" => (int) $row['id'],
            "icon" => $row['icon'],
            "title" =>  $row['name']
        );
    }
    $response['data'] = array("status" => 1, "message" => "Choose a main category id from this list for type 2B", "mainCategories" => $mainCat);
    echo json_encode($response);
    die();
}


function deleteProductFromCart()
{
    $conn = $GLOBALS['conn'];

    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['user_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No user_id is supplied");
        echo json_encode($errorData);

        die();
    }
    if (empty($input['user_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No user_id is supplied");
        echo json_encode($errorData);

        die();
    }
    $user_id = (int) $input['user_id'];
    validateUserId($conn, $user_id);
    //CHECK FOR BLOCKED CUSTOMER
    $checkk = mysqli_query($conn, "SELECT * FROM user WHERE id='$user_id' AND account_status IN('BLOCKED','INACTIVE')  ");
    if (mysqli_num_rows($checkk) > 0) {
        $errorData["data"] = array("status" => 0,   "message" => "This customer Account is Blocked or Inactive");
        echo json_encode($errorData);
        die();
    }
    //END oF BLOCKED

    if (!isset($input['product_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No product_id is supplied");
        echo json_encode($errorData);

        die();
    }
    if (empty($input['product_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No product_id is supplied");
        echo json_encode($errorData);

        die();
    }
    $product_id = (int) $input['product_id'];

    if (!isset($input['cart_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No cart_id is supplied");
        echo json_encode($errorData);

        die();
    }
    if (empty($input['cart_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No cart_id is supplied");
        echo json_encode($errorData);
        die();
    }
    $cart_id = (int) $input['cart_id'];
    $checkcart = mysqli_query($conn, "SELECT * from cart WHERE user_id = $user_id");


    if (mysqli_num_rows($checkcart) == 0) {
        $errorData["data"] = array("status" => 0,   "message" => "There is no cart items for this user.");
        echo json_encode($errorData);
        die(mysqli_error($conn));
    } else  if (mysqli_num_rows($checkcart) > 0) {
        $abc = mysqli_fetch_assoc($checkcart);

        $cartitemscounthere = mysqli_query($conn, "SELECT * FROM cart_item WHERE cart_id = $cart_id");
        if (mysqli_num_rows($cartitemscounthere) == 1) {
            //only this product is in cart
            $checkcartitem = mysqli_query($conn, "SELECT * from cart_item WHERE cart_id = $cart_id  AND product_id = $product_id LIMIT 1 ");
            if (mysqli_num_rows($checkcartitem) == 0) {
                $errorData["data"] = array("status" => 0,   "message" => "This product is not in the cart for current user.");
                echo json_encode($errorData);

                die(mysqli_error($conn));
            }
            //
            else if (mysqli_num_rows($checkcartitem) == 1) {
                $checkcartitemcount = mysqli_query($conn, "SELECT * from cart_item WHERE cart_id = $cart_id  AND product_id = $product_id ");
                mysqli_query($conn, "DELETE FROM cart WHERE id = $cart_id");
                $response["data"] = array("status" => 2, "message" => "Item deleted successfully, cart is empty now", "cart" => array());
                echo json_encode($response);
                die(mysqli_error($conn));
            }
        }
        //
        else if (mysqli_num_rows($cartitemscounthere) > 1) {

            /////////////////////////////////not only this item, some other items also there in cart for this user
            $checkcartitem = mysqli_query($conn, "SELECT * from cart_item WHERE cart_id = $cart_id  AND product_id = $product_id LIMIT 1 ");
            if (mysqli_num_rows($checkcartitem) == 0) {
                $errorData["data"] = array("status" => 0,   "message" => "This product is not in the cart for current user.");
                echo json_encode($errorData);

                die(mysqli_error($conn));
            }
            //
            else if (mysqli_num_rows($checkcartitem) == 1) {
                $checkcartitemcount = mysqli_query($conn, "SELECT * from cart_item WHERE cart_id = $cart_id  AND product_id = $product_id ");
                $xyz = mysqli_fetch_assoc($checkcartitemcount);
                $price = (int) $xyz['price'];
                mysqli_query($conn, "DELETE FROM cart_item WHERE cart_id = $cart_id  AND product_id = $product_id");

                mysqli_query($conn, "UPDATE cart SET estimate = estimate-$price WHERE user_id = $user_id");

                $cartitemcheck = mysqli_query($conn, "SELECT * from cart_item WHERE cart_id=$cart_id ");
                $cartitems = [];
                while ($rrow = mysqli_fetch_assoc($cartitemcheck)) {
                    $productId = (int) $rrow['product_id'];
                    $checkname = mysqli_query($conn, "SELECT product_title, product_description from products WHERE id = $productId");
                    $r = mysqli_fetch_assoc($checkname);
                    $product_title = $r['product_title'];
                    $product_description = $r['product_description'];

                    $checkproductname = mysqli_query($conn, "SELECT mc.name FROM main_categories mc INNER JOIN sub_categories sc ON sc.main_category_id=mc.id
                                                                INNER JOIN products p on p.category_id = sc.id AND p.id=$productId ");
                    $rw = mysqli_fetch_assoc($checkproductname);
                    $mainCategoryName = $rw['name'];

                    $cartitems[] = array(
                        "cartItemId" => (int) $rrow['id'],
                        "productId" => (int) $rrow['product_id'],
                        "productTitle" => $product_title,
                        "productDescription" => $product_description,
                        "mainCategoryName" => $mainCategoryName,
                        "count" => (int) $rrow['count'],
                        "price" => (int) $rrow['price']
                    );
                }

                $cartcheck = mysqli_query($conn, "SELECT * from cart WHERE id=$cart_id ");
                $cartrow = mysqli_fetch_assoc($cartcheck);
                $response["data"] = array(
                    "status" => 1,
                    "message" => "Item deleted from cart successfully",
                    "user_id" => $user_id,
                    "cartId" => (int) $cartrow['id'],
                    "estimate" => (int) $cartrow['estimate'],
                    "user_address_id" => (int) $cartrow['user_address_id'],
                    "date" => $cartrow['date'],
                    "slot_id" => (int) $cartrow['slot_id'],
                    "cartItems" => $cartitems
                );
                echo json_encode($response);
                die(mysqli_error($conn));
            }
        }
    }
}


function customerBookNow()
{
    $conn = $GLOBALS['conn'];

    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['user_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No user_id is supplied");
        echo json_encode($errorData);

        die();
    }
    if (empty($input['user_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No user_id is supplied");
        echo json_encode($errorData);

        die();
    }
    $user_id = (int) $input['user_id'];
    validateUserId($conn, $user_id);
    //CHECK FOR BLOCKED CUSTOMER
    $checkk = mysqli_query($conn, "SELECT * FROM user WHERE id='$user_id' AND account_status IN('BLOCKED','INACTIVE')  ");
    if (mysqli_num_rows($checkk) > 0) {
        $errorData["data"] = array("status" => 0,   "message" => "This customer Account is Blocked or Inactive");
        echo json_encode($errorData);
        die();
    }
    //END oF BLOCKED

    if (isset($input['visit_id'])) {
        //////////////////////////////////////////////////THIS IS FOR BOOKING 2A or 2B ITEM SERVICE TERM//////////////////////
        if (empty($input['visit_id'])) {
            $errorData["data"] = array("status" => 0,   "message" => "No visit_id is supplied");
            echo json_encode($errorData);

            die();
        }
        $visit_id = (int) $input['visit_id'];

        validateuserIdVisitId($conn, $user_id, $visit_id);

        ////////////////////////
        if (!isset($input['slot_id'])) {
            $errorData["data"] = array("status" => 0,   "message" => "No slot_id is supplied");
            echo json_encode($errorData);

            die();
        }
        if (empty($input['slot_id'])) {
            $errorData["data"] = array("status" => 0,   "message" => "No slot_id is supplied");
            echo json_encode($errorData);

            die();
        }
        $slot_id = (int) $input['slot_id'];
        validateSlotId($conn, $slot_id);

        if (!isset($input['date'])) {
            $errorData["data"] = array("status" => 0,   "message" => "No date is supplied");
            echo json_encode($errorData);

            die();
        }
        if (empty($input['date'])) {
            $errorData["data"] = array("status" => 0,   "message" => "No date is supplied");
            echo json_encode($errorData);

            die();
        }
        $dateNew = $input['date'];
        date_default_timezone_set('Asia/Calcutta');
        $today = date('Y-m-d');
        if (strtotime($dateNew) < strtotime($today)) {
            $errorData["data"] = array("status" => 0,   "message" => "Given date is in past.");
            echo json_encode($errorData);
            die();
        }

        $timenow =  date('h:i:s');
        $tt = mysqli_query($conn, "SELECT start FROM slot WHERE id = $slot_id");
        $ch = mysqli_fetch_assoc($tt);
        $startNew = $ch['start'];
        $minutesLeftNew = strtotime($startNew) - strtotime($timenow);
        if ($date==$today && $minutesLeftNew < 0) {
            $errorData["data"] = array("status" => 0,   "message" => "Given date is today but time is in past. Invalid input");
            echo json_encode($errorData);
            die();
        }


        $q1 = mysqli_query($conn, "SELECT b.*, s.start FROM booking_item_23 b INNER JOIN slot s ON b.slot_id  = s.id WHERE b.id = $visit_id LIMIT 1");
        $bookingdetails = mysqli_fetch_assoc($q1);

        $rescheduled_count = (int) $bookingdetails['reschedule_count'];
        if ($rescheduled_count >= 2) {
            $errorData["data"] = array("status" => 0,   "message" => "Reschedule limit exceeded; cannot Book now");
            echo json_encode($errorData);
            die();
        }

        $status = $bookingdetails['status'];
        if ($status == 'CANCELLED') {
            $errorData["data"] = array("status" => 0,   "message" => "This booking already cancelled");
            echo json_encode($errorData);
            die();
        }
        //////////////////////

        $user_address_id = (int) $input['user_address_id'];
        $xyz = mysqli_query($conn, "SELECT zone_id FROM user_address WHERE id = $user_address_id");
        $bbc = mysqli_fetch_assoc($xyz);
        $zone_id = (int) $bbc['zone_id'];
        checkSlotAvailable($conn, $slot_id, $zone_id, $dateNew);

        $updated = date('Y-m-d');

        $q11 = mysqli_query($conn, "SELECT b.* FROM booking_item_23 b WHERE b.id = $visit_id LIMIT 1");
        $bookingdetailss = mysqli_fetch_assoc($q11);
        $main_category_id = (int) $bookingdetailss['main_category_id'];
        $checkdiv = mysqli_query($conn, "SELECT division FROM main_categories WHERE id = $main_category_id");
        $divget = mysqli_fetch_assoc($checkdiv);
        $division = $divget['division'];
        // echo $division;

        if ($division == 'A') {
            $booknow = mysqli_query($conn, "UPDATE booking_item_23 SET status = 'PLACED', date = '$dateNew', slot_id = $slot_id, user_address_id = $user_address_id, updated = '$updated' WHERE id = $visit_id  ");
            if ($booknow) {
                $errorData["data"] = array("status" => 1,   "message" => "Booking made by the user successfully For TYPE 2A");
                echo json_encode($errorData);
                die();
            } else {
                $errorData["data"] = array("status" => 0,   "message" => "Internal server error");
                echo json_encode($errorData);
                die();
            }
        } else if ($division == 'B') {
            if (!isset($input['choose'])) {
                $errorData["data"] = array("status" => 0,   "message" => "This visit_id is for type 2B; No choose is supplied");
                echo json_encode($errorData);

                die();
            }
            if (empty($input['choose'])) {
                $errorData["data"] = array("status" => 0,   "message" => "This visit_id is for type 2B; No choose is supplied");
                echo json_encode($errorData);

                die();
            }
            $main_category_id_chosen = (int) $input['choose'];

            $booknow = mysqli_query($conn, "UPDATE booking_item_23 SET status = 'PLACED', date = '$dateNew', slot_id = $slot_id, user_address_id = $user_address_id, main_category_id_chosen = $main_category_id_chosen, updated = '$updated' WHERE id = $visit_id  ");

            if ($booknow) {
                $errorData["data"] = array("status" => 1,   "message" => "Booking made by the user successfully For TYPE 2B");
                echo json_encode($errorData);
                die();
            } else {
                $errorData["data"] = array("status" => 0,   "message" => "Internal server error");
                echo json_encode($errorData);
                die();
            }
        }
        /////////////////////////////////////END OF BOOKING FOR SERVICE TERM/////////////////////////////////////////
    }

    if (!isset($input['cart_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No cart_id is suppliedd");
        echo json_encode($errorData);

        die();
    }
    if (empty($input['cart_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No cart_id is supplied");
        echo json_encode($errorData);
        die();
    }
    $cart_id = (int) $input['cart_id'];
    $checkcart = mysqli_query($conn, "SELECT * from cart WHERE user_id = $user_id AND id = $cart_id");

    if (mysqli_num_rows($checkcart) == 0) {
        $errorData["data"] = array("status" => 0,   "message" => "invalid input");
        echo json_encode($errorData);
        die(mysqli_error($conn));
    }

    $abc = mysqli_fetch_assoc($checkcart);
    $home_category_id = (int) $abc['home_category_id'];
    date_default_timezone_set('Asia/Calcutta');


    if ($home_category_id == 1) {

        //////////////////////////////TYPE 1///////////////////////////////////////////
        $estimate = (int) $abc['estimate'];
        $user_address_id = (int) $abc['user_address_id'];
        $date =  $abc['date'];
        $slot_id = (int) $abc['slot_id'];
        $rescheduled_count = 0;


        $xyz = mysqli_query($conn, "SELECT zone_id FROM user_address WHERE id = $user_address_id");
        $bbc = mysqli_fetch_assoc($xyz);
        $zone_id = (int) $bbc['zone_id'];

        checkSlotAvailable($conn, $slot_id, $zone_id, $date);

        $taxcheck = mysqli_query($conn, "SELECT tax_percent FROM home_categories WHERE id = $home_category_id");
        $aa = mysqli_fetch_assoc($taxcheck);
        $tax_percent = (int) $aa['tax_percent'];
        $tax_amount = ceil(($tax_percent * $estimate) / 100);
        $total = $estimate + $tax_amount;
        $amount_payable = $total;
        $credits = 3;
        $status = 'PLACED';
        $payment = 'PENDING';

        $today_date =  date('Y-m-d');
        $created = $today_date;
        $updated = $today_date;



        $today = date('ymd');
        $fircheck = mysqli_query($conn, "SELECT * FROM booking WHERE id LIKE '$today%' ORDER BY id DESC LIMIT 1");

        if (mysqli_num_rows($fircheck) == 0) {
            $booking_id = $today . '001';
        } else {
            $zz = mysqli_fetch_assoc($fircheck);
            $testt = (int) $zz['id'];
            $booking_id = $testt + 1;
        }


        $q1 = mysqli_query($conn, "INSERT INTO booking(id, home_category_id, user_id, estimate, user_address_id, date, slot_id, rescheduled_count, tax_percent, tax_amount, total, amount_payable, credits, status, payment, created, updated)
                                VALUES($booking_id, $home_category_id, $user_id, $estimate, $user_address_id, '$date', $slot_id, $rescheduled_count, $tax_percent, $tax_amount, $total, $amount_payable, $credits, '$status', '$payment', '$created','$updated' )");

        $cartitems = mysqli_query($conn, "SELECT * FROM cart_item WHERE cart_id = $cart_id");
        $params = NULL;
        while ($cartrow = mysqli_fetch_assoc($cartitems)) {
            $product_id = (int) $cartrow['product_id'];
            $count = (int) $cartrow['count'];
            $price = (int) $cartrow['price'];

            $params = "(" . $booking_id . "," . $product_id . "," . $count . "," . $price . "),";
        }
        $params = rtrim($params, ',');

        $q2 = mysqli_query($conn, "INSERT INTO booking_item(booking_id, product_id, count, price) VALUES $params");

        $q3 = mysqli_query($conn, "DELETE FROM cart WHERE user_id = $user_id");

        if ($q1 and $q2 and $q3) {
            $errorData["data"] = array("status" => 1,   "message" => "Type 1 - Booking made by the user successfully");
            echo json_encode($errorData);
            die(mysqli_error($conn));
        } else {
            $errorData["data"] = array("status" => 0,   "message" => "internal server error");
            echo json_encode($errorData);
            die(mysqli_error($conn));
        }
    } else if ($home_category_id == 2) {
        //////////////////////////////////////////////////////TYPE 2////////////////////////////////////////////////////
        $estimate = (int) $abc['estimate'];
        $taxcheck = mysqli_query($conn, "SELECT tax_percent FROM home_categories WHERE id = $home_category_id");
        $aa = mysqli_fetch_assoc($taxcheck);
        $tax_percent = (int) $aa['tax_percent'];
        $tax_amount = ceil(($tax_percent * $estimate) / 100);

        $total = $estimate + $tax_amount;
        $amount_payable = $total;

        $status = 'PLACED';
        $payment = 'PENDING';

        $today_date =  date('Y-m-d');
        $created = $today_date;
        $updated = $today_date;
        $expiry =  date('Y-m-d', strtotime('+1 year'));

        $today = date('ymd');
        $fircheck = mysqli_query($conn, "SELECT * FROM booking WHERE id LIKE '$today%' ORDER BY id DESC LIMIT 1");

        if (mysqli_num_rows($fircheck) == 0) {
            $booking_id = $today . '001';
        } else {
            $zz = mysqli_fetch_assoc($fircheck);
            $testt = (int) $zz['id'];
            $booking_id = $testt + 1;
        }

        $q1 = mysqli_query($conn, "INSERT INTO booking(id, home_category_id, user_id, estimate, tax_percent, tax_amount, total, amount_payable, status, payment, created, updated, expiry)
                                VALUES($booking_id, $home_category_id, $user_id, $estimate, $tax_percent, $tax_amount, $total, $amount_payable, '$status', '$payment', '$created','$updated', '$expiry' )");

        $cartitems = mysqli_query($conn, "SELECT * FROM cart_item WHERE cart_id = $cart_id");
        $params = NULL;

        $cartrow = mysqli_fetch_assoc($cartitems);
        $main_category_id = (int) $cartrow['main_category_id'];
        $quantity = (int) $cartrow['count'];
        $visits = (int) $cartrow['visits'];

        $credits = 3;

        $i = 1;
        while ($i <= $visits) {
            $params .= "(" . $booking_id . "," . $main_category_id . "," . $quantity . "," . $i . "," . $credits . ",'" . $created . "','" . $updated . "'" . "),";
            $i++;
        }
        $params = rtrim($params, ',');

        $q2 = mysqli_query($conn, "INSERT INTO booking_item_23(booking_id, main_category_id, quantity, visit_number, credits, created, updated) VALUES $params");

        $q3 = mysqli_query($conn, "DELETE FROM cart WHERE user_id = $user_id");

        if ($q1 and $q2 and $q3) {
            $errorData["data"] = array("status" => 1,   "message" => "Type 2 - Booking made by the user successfully");
            echo json_encode($errorData);
            die(mysqli_error($conn));
        } else {
            $errorData["data"] = array("status" => 0,   "message" => "internal server error");
            echo json_encode($errorData);
            die(mysqli_error($conn));
        }
    }
}

function checkSlotAvailable($conn, $slot_id, $zone_id, $date)
{
    if (strtotime($date) < strtotime(date('Y-m-d'))) {
        $errorData["data"] = array("status" => 0,   "message" => "Past date is supplied");
        echo json_encode($errorData);
        die(mysqli_error($conn));
    }

    checkZoneId($conn, $zone_id);

    $res = mysqli_query($conn, "SELECT slot_id, 
                (SELECT count(emp_id) as count from available_slot a WHERE a.slot_id = b.slot_id AND  date='$date' AND available='YES' AND zone_id=$zone_id) as count,
                s.name as name
                from available_slot b
            INNER JOIN slot s ON s.id = b.slot_id
            WHERE b.slot_id = $slot_id");

    if (mysqli_num_rows($res)) {
        $current_row = mysqli_fetch_assoc($res);
        //check already booked
        $bookedalreadycheck = mysqli_query($conn, "SELECT b.slot_id,COUNT(b.emp_id) as count FROM booking b INNER JOIN slot s ON b.slot_id = s.id
                                                    	WHERE b.slot_id=$slot_id
                                                        AND b.date='$date'
                                                        AND b.status IN ('PLACED','ACCEPTED','RESCHEDULED')
                                                        AND b.payment = 'PENDING'");
        $bookedCount = 0;
        if (mysqli_num_rows($bookedalreadycheck) > 0) {
            $thisrow = mysqli_fetch_assoc($bookedalreadycheck);
            $bookedCount = (int) $thisrow['count'];
        }
        $resultantCount = (int) $current_row['count'] - $bookedCount;
        //already booked block ends here
        if ($resultantCount > 0) {
            //count available now
            return true;
        }
        //
        else {
            //count = 0, so unavailable at the moment
            $errorData["data"] = array("status" => 0,   "message" => "This slot is not available for given data");
            echo json_encode($errorData);
            die(mysqli_error($conn));
        }
    }
    //
    else {
        $errorData["data"] = array("status" => 0,   "message" => "This slot is not available");
        echo json_encode($errorData);
        die(mysqli_error($conn));
    }
}


function viewCustomerBooking()
{
    $conn = $GLOBALS['conn'];
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($_GET['user_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No user_id is supplied");
        echo json_encode($errorData);

        die();
    }
    if (empty($_GET['user_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No user_id is supplied");
        echo json_encode($errorData);

        die();
    }
    $user_id = (int) $_GET['user_id'];
    validateUserId($conn, $user_id);
    //CHECK FOR BLOCKED CUSTOMER
    $checkk = mysqli_query($conn, "SELECT * FROM user WHERE id='$user_id' AND account_status IN('BLOCKED','INACTIVE')  ");
    if (mysqli_num_rows($checkk) > 0) {
        $errorData["data"] = array("status" => 0,   "message" => "This customer Account is Blocked or Inactive");
        echo json_encode($errorData);
        die();
    }
    //END oF BLOCKED

    if (!isset($_GET['booking_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No booking_id is supplied");
        echo json_encode($errorData);

        die();
    }
    if (empty($_GET['booking_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No booking_id is supplied");
        echo json_encode($errorData);
        die();
    }
    $booking_id = (int) $_GET['booking_id'];
    validateuserIdBookingId($conn, $user_id, $booking_id);

    $q1 = mysqli_query($conn, "SELECT * FROM booking WHERE user_id = $user_id AND id = $booking_id LIMIT 1");
    $bookingdetails = mysqli_fetch_assoc($q1);
    $otp = (int) $bookingdetails['otp'];
    $emp_id = (int) $bookingdetails['emp_id'];
    $q2 = mysqli_query($conn, "SELECT e.*, ROUND(AVG(stars),2) AS rating FROM employee e INNER JOIN reviews r ON e.id = r.emp_id WHERE e.id = $emp_id");
    $employeerow = mysqli_fetch_assoc($q2);
    $main_category_services = $employeerow['main_category_services'];
    $main_category_services = substr($main_category_services, 0, 1);
    $q3 = mysqli_query($conn, "SELECT name FROM main_categories WHERE id = '$main_category_services'");
    $main_cat_name = mysqli_fetch_assoc($q3);
    $mc_name = $main_cat_name['name'];


    $employeeDetails = array("name" => $employeerow['name'], "country_code" => $employeerow['country_code'], "cell" => $employeerow['cell'], "main_category" => $mc_name, "ratings" => $employeerow['rating']==NULL?'':$employeerow['rating']);

    $rescheduled_count = (int) $bookingdetails['rescheduled_count'];
    $booking_status =  $bookingdetails['status'];
    $servicer_status =  $bookingdetails['servicer_status'];
    $payment_type =  $bookingdetails['payment_type'];
    $payment =  $bookingdetails['payment'];
    $booking_date =  $bookingdetails['date'];
    $estimate =  (int) $bookingdetails['estimate'];
    $tax_percent =  (int) $bookingdetails['tax_percent'];
    $tax_amount =  (int) $bookingdetails['tax_amount'];
    $total =  (int) $bookingdetails['total'];
    $coupon =   $bookingdetails['coupon'];

    $q4 = mysqli_query($conn, "SELECT description FROM coupon WHERE code = '$coupon' ");
    $coupondesc = mysqli_fetch_assoc($q4);
    $coupon_description =   $coupondesc['description'];

    $coupon_reduce =  (int) $bookingdetails['coupon_reduce'];
    $wallet_reduce =  (int) $bookingdetails['wallet_reduce'];
    $amount_payable =  (int) $bookingdetails['amount_payable'];

    $user_address_id =  (int) $bookingdetails['user_address_id'];
    $q5 = mysqli_query($conn, "SELECT * FROM user_address WHERE id = $user_address_id");
    $addressDetails = mysqli_fetch_assoc($q5);

    $user_address_details = array("location" => $addressDetails['location'], "address" => $addressDetails['address'], "latitude" => $addressDetails['latitude'], "longitude" => $addressDetails['longitude'], "zone_id" => (int) $addressDetails['zone_id']);

    $slot_id =  (int) $bookingdetails['slot_id'];
    $q6 = mysqli_query($conn, "SELECT name FROM slot WHERE id = $slot_id");
    $slotname = mysqli_fetch_assoc($q6);
    $slot = $slotname['name'];

    $updated = $bookingdetails['updated'];

    $q7 = mysqli_query($conn, "SELECT bi.*, product_title, product_description, mc.name FROM booking_item bi INNER JOIN products p ON bi.product_id = p.id INNER JOIN main_categories mc ON p.category_id = mc.id WHERE booking_id = $booking_id");;
    while ($bookingItem = mysqli_fetch_assoc($q7)) {
        $bookingItemsArray[] = array(
            "bookingItemId" => (int) $bookingItem['id'],
            "productId" => (int) $bookingItem['product_id'],
            "productTitle" =>  $bookingItem['product_title'],
            "productDescription" =>  $bookingItem['product_description'],
            "mainCategoryName" => $bookingItem['name'],
            "count" => (int) $bookingItem['count'],
            "price" => (int) $bookingItem['price']
        );
    }

    $response['data'] = array(
        "status" => 1,
        "message" => "Booking Detail",
        "user_id" => (int) $user_id,
        "otp" => (int) $otp,
        "employee_details" => $employeeDetails,
        "booking_id" => (int) $booking_id,
        "rescheduled_count" => (int) $rescheduled_count,
        "booking_status" => $booking_status,
        "servicer_status" => $servicer_status,
        "payment_type" => $payment_type,
        "payment" => $payment,
        "booking_date" =>  $booking_date,
        "estimate" => (int) $estimate,
        "tax_percent" => (int) $tax_percent,
        "tax_amount" => (int) $tax_amount,
        "total" => (int) $total,
        "coupon" => $coupon,
        "coupon_description" => $coupon_description,
        "coupon_reduce" => (int) $coupon_reduce,
        "wallet_reduce" => (int) $wallet_reduce,
        "amount_payable" => (int) $amount_payable,
        "user_address_details" => $user_address_details,
        "slot" => $slot,
        "updated" => $updated,
        "booking_items" => $bookingItemsArray
    );
    echo json_encode($response);
    die();
}


function viewCustomerBookings()
{
    $conn = $GLOBALS['conn'];
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($_GET['user_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No user_id is supplied");
        echo json_encode($errorData);

        die();
    }
    if (empty($_GET['user_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No user_id is supplied");
        echo json_encode($errorData);

        die();
    }
    $user_id = (int) $_GET['user_id'];
    validateUserId($conn, $user_id);
    //CHECK FOR BLOCKED CUSTOMER
    $checkk = mysqli_query($conn, "SELECT * FROM user WHERE id='$user_id' AND account_status IN('BLOCKED','INACTIVE')  ");
    if (mysqli_num_rows($checkk) > 0) {
        $errorData["data"] = array("status" => 0,   "message" => "This customer Account is Blocked or Inactive");
        echo json_encode($errorData);
        die();
    }
    //END oF BLOCKED

    $q1 = mysqli_query($conn, "SELECT b.*, s.name FROM booking b INNER JOIN slot s ON b.slot_id = s.id WHERE user_id = $user_id AND home_category_id=1");
    if (mysqli_num_rows($q1) == 0) {
        $bookingsArray = array();
    }
    while ($bookingrows = mysqli_fetch_assoc($q1)) {
        $bookingsArray[] = array(
            "booking_id" => (int) $bookingrows['id'],
            "estimate" => (int) $bookingrows['estimate'],
            "date" => $bookingrows['date'],
            "slot" => $bookingrows['name'],
            "booking_status" => $bookingrows['status'],
            "servicer_status" => $bookingrows['servicer_status'],
            "payment" => $bookingrows['payment'],
            "payment_type" => $bookingrows['payment_type'],
            "updated" => $bookingrows['updated']
        );
    }

    $q2 = mysqli_query($conn, "SELECT * FROM booking WHERE user_id = $user_id AND home_category_id=2");
    if (mysqli_num_rows($q2) == 0) {
        $subscriptionsArray = array();
    }

    while ($bookingrows = mysqli_fetch_assoc($q2)) {

        $booking_id = (int) $bookingrows['id'];
        $qq = mysqli_query($conn, "SELECT mc.division, mc.icon, mc.name, bi23.status, bi23.servicer_status, bi23.payment, bi23.payment_type,
                                    (SELECT name FROM main_categories mcc WHERE mcc.id = bi23.main_category_id_chosen) AS main_category_chosen_name
                                    FROM booking_item_23 bi23 INNER JOIN main_categories mc ON bi23.main_category_id = mc.id WHERE bi23.booking_id= $booking_id");
        $subscripdetails = mysqli_fetch_assoc($qq);

        $checkavailable = mysqli_query($conn, "SELECT COUNT(id) AS services_available FROM `booking_item_23` WHERE `booking_id` = $booking_id AND status IS NULL AND payment IS NULL");
        $aa = mysqli_fetch_assoc($checkavailable);


        $subscriptionsArray[] = array(
            "booking_id" => (int) $bookingrows['id'],
            "booking_status" => $bookingrows['status'],
            "servicer_status" => $bookingrows['servicer_status'],
            "payment" => $bookingrows['payment'],
            "payment_type" => $bookingrows['payment_type'],
            "expiry" => $bookingrows['expiry'],
            "division" => $subscripdetails['division'],
            "main_category_icon" => $subscripdetails['icon'],
            "main_category_name" => $subscripdetails['name'],
            "main_category_id_chosen" => (int) $subscripdetails['main_category_id_chosen'],
            "main_category_chosen_name" => $subscripdetails['main_category_chosen_name'],
            "services_available" => (int) $aa['services_available']
        );
    }

    $response['data'] = array(
        "status" => 1, "message" => "Bookings and Subscriptions List for current user",
        "bookings" => $bookingsArray, "subscriptions" => $subscriptionsArray
    );

    echo json_encode($response);
    die();
}



function customerCancelBooking()
{
    $conn = $GLOBALS['conn'];
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['user_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No user_id is supplied");
        echo json_encode($errorData);

        die();
    }
    if (empty($input['user_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No user_id is supplied");
        echo json_encode($errorData);

        die();
    }
    $user_id = (int) $input['user_id'];
    validateUserId($conn, $user_id);
    //CHECK FOR BLOCKED CUSTOMER
    $checkk = mysqli_query($conn, "SELECT * FROM user WHERE id='$user_id' AND account_status IN('BLOCKED','INACTIVE')  ");
    if (mysqli_num_rows($checkk) > 0) {
        $errorData["data"] = array("status" => 0,   "message" => "This customer Account is Blocked or Inactive");
        echo json_encode($errorData);
        die();
    }
    //END oF BLOCKED


    if (!isset($input['booking_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No booking_id is supplied");
        echo json_encode($errorData);

        die();
    }
    if (empty($input['booking_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No booking_id is supplied");
        echo json_encode($errorData);

        die();
    }
    $booking_id = (int) $input['booking_id'];

    validateuserIdBookingId($conn, $user_id, $booking_id);




    $q1 = mysqli_query($conn, "SELECT b.*, s.start FROM booking b INNER JOIN slot s ON b.slot_id  = s.id WHERE user_id = $user_id AND b.id = $booking_id LIMIT 1");
    $bookingdetails = mysqli_fetch_assoc($q1);

    $status = $bookingdetails['status'];
    if ($status == 'CANCELLED') {
        $errorData["data"] = array("status" => 0,   "message" => "This booking already cancelled");
        echo json_encode($errorData);
        die();
    }

    $home_category_id = (int) $bookingdetails['home_category_id'];
    if ($home_category_id == 2) {
        $errorData["data"] = array("status" => 0,   "message" => "This booking is not from TYPE 1. Cannot reschedule this booking");
        echo json_encode($errorData);
        die();
    }

    $date = $bookingdetails['date'];
    date_default_timezone_set('Asia/Calcutta');
    $today = date('Y-m-d');
    if (strtotime($date) < strtotime($today)) {
        $errorData["data"] = array("status" => 0,   "message" => "Booked date is in past. Invalid input");
        echo json_encode($errorData);
        die();
    }


    $start = $bookingdetails['start'];
    date_default_timezone_set('Asia/Calcutta');
    $updated = date('Y-m-d');

    $timenow =  date('Y-m-d h:i:s');
    $booked_time = $date . " " . $start;
    $minutesLeft = CEIL((strtotime($booked_time) - strtotime($timenow)) / 60);
    if ($minutesLeft < 0) {
        $errorData["data"] = array("status" => 0,   "message" => "Booked time is in past. Invalid input");
        echo json_encode($errorData);
        die();
    } else if ($minutesLeft < 120) {
        $errorData["data"] = array("status" => 0,   "message" => "Booked time is less than 2 hours. Cannot cancel this booking now");
        echo json_encode($errorData);
        die();
    } else if ($minutesLeft >= 120) {
        $cancel = mysqli_query($conn, "UPDATE booking SET status = 'CANCELLED', servicer_status='Cancelled', payment = 'CANCELLED', payment_type=NULL, updated = '$updated' WHERE user_id = $user_id AND id = $booking_id  ");
        if ($cancel) {
            $errorData["data"] = array("status" => 1,   "message" => "This booking ID is cancelled by user successfully");
            echo json_encode($errorData);
            die();
        } else {
            $errorData["data"] = array("status" => 0,   "message" => "Internal server error");
            echo json_encode($errorData);
            die();
        }
    }
}

function customerRescheduleBooking()
{
    $conn = $GLOBALS['conn'];
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['user_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No user_id is supplied");
        echo json_encode($errorData);

        die();
    }
    if (empty($input['user_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No user_id is supplied");
        echo json_encode($errorData);

        die();
    }
    $user_id = (int) $input['user_id'];
    validateUserId($conn, $user_id);
    //CHECK FOR BLOCKED CUSTOMER
    $checkk = mysqli_query($conn, "SELECT * FROM user WHERE id='$user_id' AND account_status IN('BLOCKED','INACTIVE')  ");
    if (mysqli_num_rows($checkk) > 0) {
        $errorData["data"] = array("status" => 0,   "message" => "This customer Account is Blocked or Inactive");
        echo json_encode($errorData);
        die();
    }
    //END oF BLOCKED


    if (!isset($input['booking_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No booking_id is supplied");
        echo json_encode($errorData);

        die();
    }
    if (empty($input['booking_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No booking_id is supplied");
        echo json_encode($errorData);

        die();
    }
    $booking_id = (int) $input['booking_id'];

    validateuserIdBookingId($conn, $user_id, $booking_id);


    if (!isset($input['slot_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No slot_id is supplied");
        echo json_encode($errorData);

        die();
    }
    if (empty($input['slot_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No slot_id is supplied");
        echo json_encode($errorData);

        die();
    }
    $slot_id = (int) $input['slot_id'];
    validateSlotId($conn, $slot_id);

    if (!isset($input['date'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No date is supplied");
        echo json_encode($errorData);

        die();
    }
    if (empty($input['date'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No date is supplied");
        echo json_encode($errorData);

        die();
    }
    $dateNew = $input['date'];
    date_default_timezone_set('Asia/Calcutta');
    $today = date('Y-m-d');
    if (strtotime($dateNew) < strtotime($today)) {
        $errorData["data"] = array("status" => 0,   "message" => "Given date is in past.");
        echo json_encode($errorData);
        die();
    }

    $timenow =  date('h:i:s');
    $tt = mysqli_query($conn, "SELECT start FROM slot WHERE id = $slot_id");
    $ch = mysqli_fetch_assoc($tt);
    $startNew = $ch['start'];
    $minutesLeftNew = strtotime($startNew) - strtotime($timenow);
    if ($minutesLeftNew < 0) {
        $errorData["data"] = array("status" => 0,   "message" => "Given date is today but time is in past. Invalid input");
        echo json_encode($errorData);
        die();
    }

    $q1 = mysqli_query($conn, "SELECT b.*, s.start FROM booking b INNER JOIN slot s ON b.slot_id  = s.id WHERE user_id = $user_id AND b.id = $booking_id LIMIT 1");
    $bookingdetails = mysqli_fetch_assoc($q1);

    $rescheduled_count = (int) $bookingdetails['rescheduled_count'];
    if ($rescheduled_count >= 2) {
        $errorData["data"] = array("status" => 0,   "message" => "Reschedule limit exceeded; cannot reschedule now");
        echo json_encode($errorData);
        die();
    }

    $status = $bookingdetails['status'];
    if ($status == 'CANCELLED') {
        $errorData["data"] = array("status" => 0,   "message" => "This booking already cancelled");
        echo json_encode($errorData);
        die();
    }

    $home_category_id = (int) $bookingdetails['home_category_id'];
    if ($home_category_id == 2) {
        $errorData["data"] = array("status" => 0,   "message" => "This booking is not from TYPE 1. Cannot reschedule this booking");
        echo json_encode($errorData);
        die();
    }

    $date = $bookingdetails['date'];
    date_default_timezone_set('Asia/Calcutta');
    $today = date('Y-m-d');
    if (strtotime($date) < strtotime($today)) {
        $errorData["data"] = array("status" => 0,   "message" => "Booked date is in past. Invalid input");
        echo json_encode($errorData);
        die();
    }


    $start = $bookingdetails['start'];
    date_default_timezone_set('Asia/Calcutta');
    $updated = date('Y-m-d');

    $timenow =  date('Y-m-d h:i:s');
    $booked_time = $date . " " . $start;
    $minutesLeft = CEIL((strtotime($booked_time) - strtotime($timenow)) / 60);
    if ($minutesLeft < 0) {
        $errorData["data"] = array("status" => 0,   "message" => "Booked time is in past. Invalid input");
        echo json_encode($errorData);
        die();
    } else if ($minutesLeft < 120) {
        $errorData["data"] = array("status" => 0,   "message" => "Booked time is less than 2 hours. Cannot cancel this booking now");
        echo json_encode($errorData);
        die();
    }

    $user_address_id = (int) $bookingdetails['user_address_id'];
    $xyz = mysqli_query($conn, "SELECT zone_id FROM user_address WHERE id = $user_address_id");
    $bbc = mysqli_fetch_assoc($xyz);
    $zone_id = (int) $bbc['zone_id'];
    checkSlotAvailable($conn, $slot_id, $zone_id, $dateNew);


    if ($minutesLeft >= 120) {
        $reschedule = mysqli_query($conn, "UPDATE booking SET rescheduled_count = $rescheduled_count+1, status = 'RESCHEDULED', emp_id = NULL, date = '$dateNew', slot_id = $slot_id, updated = '$updated' WHERE user_id = $user_id AND id = $booking_id  ");
        if ($reschedule) {
            $errorData["data"] = array("status" => 1,   "message" => "This booking ID is rescheduled by user successfully");
            echo json_encode($errorData);
            die();
        } else {
            $errorData["data"] = array("status" => 0,   "message" => "Internal server error");
            echo json_encode($errorData);
            die();
        }
    }
}


function validateSlotId($conn, $slot_id)
{
    $check = mysqli_query($conn, "SELECT * FROM slot WHERE id = $slot_id");
    if (mysqli_num_rows($check) == 0) {
        $errorData["data"] = array("status" => 0,   "message" => "Invalid slot_id is passed");
        echo json_encode($errorData);
        die();
    } else {
        return true;
    }
}

function customerViewSubscription()
{

    $conn = $GLOBALS['conn'];
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($_GET['user_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No user_id is supplied");
        echo json_encode($errorData);

        die();
    }
    if (empty($_GET['user_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No user_id is supplied");
        echo json_encode($errorData);

        die();
    }
    $user_id = (int) $_GET['user_id'];
    validateUserId($conn, $user_id);
    //CHECK FOR BLOCKED CUSTOMER
    $checkk = mysqli_query($conn, "SELECT * FROM user WHERE id='$user_id' AND account_status IN('BLOCKED','INACTIVE')  ");
    if (mysqli_num_rows($checkk) > 0) {
        $errorData["data"] = array("status" => 0,   "message" => "This customer Account is Blocked or Inactive");
        echo json_encode($errorData);
        die();
    }
    //END oF BLOCKED

    if (!isset($_GET['booking_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No booking_id is supplied");
        echo json_encode($errorData);

        die();
    }
    if (empty($_GET['booking_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No booking_id is supplied");
        echo json_encode($errorData);
        die();
    }
    $booking_id = (int) $_GET['booking_id'];
    validateuserIdBookingId($conn, $user_id, $booking_id);

    $q1 = mysqli_query($conn, "SELECT
                                    bb.created,
                                    bb.expiry,
                                    bb.home_category_id,
                                    (SELECT COUNT(id) FROM booking_item_23 bii WHERE bii.booking_id = bi.`booking_id` AND bii.status IS NULL) AS services_available,
                                    ROUND((((SELECT COUNT(id) FROM booking_item_23 bii WHERE bii.booking_id = bi.`booking_id` AND bii.status IS NULL LIMIT 1)*rv.minutes_per_visit)/60),1) AS hours_left,
                                    bi.*,
                                    mc.division,
                                    mc.name,
                                    mc.icon,
                                    mc.home_category_id,
                                    (SELECT name FROM main_categories mcc WHERE mcc.id = bi.`main_category_id_chosen`) AS main_category_chosen_name
                                    FROM `booking_item_23` bi
                                    INNER JOIN main_categories mc
                                    ON mc.id = bi.`main_category_id` 
                                    INNER JOIN rate_visits rv
                                    ON rv.main_category_id = bi.`main_category_id`
                                    INNER JOIN booking bb 
                                    ON bb.id = bi.`booking_id`
                                    WHERE bi.`booking_id`= $booking_id
                                    GROUP BY bi.`id`");


    while ($row = mysqli_fetch_assoc($q1)) {
        $services_list[] = array(
            "visit_id" => (int) $row['id'],
            "icon" => $row['icon'],
            "name" => "Service Term " . (int) $row['visit_number'],
            "status" =>  $row['status'] == NULL ? 'Available' : $row['status'],
            "booked_on" =>  $row['date'] == NULL ? '' : $row['date']
        );
    }

    $q2 = mysqli_query($conn, "SELECT
                                    bb.created,
                                    bb.expiry,
                                    bb.home_category_id,
                                    (SELECT COUNT(id) FROM booking_item_23 bii WHERE bii.booking_id = bi.`booking_id` AND bii.status IS NULL) AS services_available,
                                    ROUND((((SELECT COUNT(id) FROM booking_item_23 bii WHERE bii.booking_id = bi.`booking_id` AND bii.status IS NULL LIMIT 1)*rv.minutes_per_visit)/60),1) AS hours_left,
                                    bi.*,
                                    mc.division,
                                    mc.name,
                                    mc.icon,
                                    mc.home_category_id,
                                    (SELECT name FROM main_categories mcc WHERE mcc.id = bi.`main_category_id_chosen`) AS main_category_chosen_name
                                    FROM `booking_item_23` bi
                                    INNER JOIN main_categories mc
                                    ON mc.id = bi.`main_category_id` 
                                    INNER JOIN rate_visits rv
                                    ON rv.main_category_id = bi.`main_category_id`
                                    INNER JOIN booking bb 
                                    ON bb.id = bi.`booking_id`
                                    WHERE bi.`booking_id`= $booking_id
                                    GROUP BY bi.`id`
                                    LIMIT 1");
    $row2 = mysqli_fetch_assoc($q2);
    $q3 = mysqli_query($conn, "SELECT created FROM booking WHERE id = $booking_id");
    $tx = mysqli_fetch_assoc($q3);
    $subs = $tx['created'];
    $response['data'] = array(
        "status" => 1,
        "message" => "Services List for current user's subscription",
        "booking_id" => $booking_id,
        "home_category_id" => (int) $row2['home_category_id'],
        "division" => $row2['division'],
        "main_category_name" => $row2['name'],
        "main_category_icon" => $row2['icon'],
        "main_category_chosen_name" => $row2['main_category_chosen_name'] == NULL ? '' : $row2['main_category_chosen_name'],
        "services_available" => (int) $row2['services_available'],
        "hours_left" => (int) $row2['hours_left'],
        "subscribed_on" => $subs,
        "expiry" => $row2['expiry'],
        "services_list" => $services_list
    );
    echo json_encode($response);
    die();
}

function customerViewSingleTerm()
{
    $conn = $GLOBALS['conn'];
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($_GET['user_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No user_id is supplied");
        echo json_encode($errorData);

        die();
    }
    if (empty($_GET['user_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No user_id is supplied");
        echo json_encode($errorData);

        die();
    }
    $user_id = (int) $_GET['user_id'];
    validateUserId($conn, $user_id);
    //CHECK FOR BLOCKED CUSTOMER
    $checkk = mysqli_query($conn, "SELECT * FROM user WHERE id='$user_id' AND account_status IN('BLOCKED','INACTIVE')  ");
    if (mysqli_num_rows($checkk) > 0) {
        $errorData["data"] = array("status" => 0,   "message" => "This customer Account is Blocked or Inactive");
        echo json_encode($errorData);
        die();
    }
    //END oF BLOCKED

    if (!isset($_GET['visit_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No visit_id is supplied");
        echo json_encode($errorData);

        die();
    }
    if (empty($_GET['visit_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No visit_id is supplied");
        echo json_encode($errorData);
        die();
    }
    $visit_id = (int) $_GET['visit_id'];
    validateuserIdVisitId($conn, $user_id, $visit_id);

    $q1 = mysqli_query($conn, "SELECT mc.name,
                (SELECT name FROM main_categories mcc WHERE mcc.id = bi.main_category_id_chosen)AS main_category_chosen_name,
                bi.* FROM booking_item_23 bi INNER JOIN main_categories mc ON mc.id = bi.`main_category_id` WHERE bi.id = $visit_id");
    $row = mysqli_fetch_assoc($q1);

    $term_name = "Service Term - " . $row['visit_number'];
    $otp = (int) $row['otp'];
    $main_category_id = (int) $row['main_category_id'];
    $main_category_name = $row['name'];
    $main_category_id_chosen = $row['main_category_id_chosen'];
    $main_category_chosen_name = $row['main_category_chosen_name'];
    $device_serial_number = $row['device_serial_number'];
    $booking_id = (int) $row['booking_id'];
    $rescheduled_count = (int) $row['reschedule_count'];
    $booking_status = $row['status'];
    $servicer_status = $row['servicer_status'];
    $booking_date = $row['date'];

    $emp_id = (int) $row['emp_id'];
    $empquery = mysqli_query($conn, "SELECT ROUND(AVG(stars),2) AS ratings,e.* FROM employee e INNER JOIN reviews r ON r.emp_id = e.id WHERE e.id = $emp_id LIMIT 1");
    $row2 = mysqli_fetch_assoc($empquery);
    $main_category_services = $row2['main_category_services'];
    $main_category_services = substr($main_category_services, 0, 1);
    $q3 = mysqli_query($conn, "SELECT name FROM main_categories WHERE id = '$main_category_services'");
    $main_cat_name = mysqli_fetch_assoc($q3);
    $mc_name = $main_cat_name['name'];
    $employeeDetails = array("name" => $row2['name'], "country_code" => $row2['country_code'], "cell" => $row2['cell'], "main_category" => $mc_name, "ratings" => $row2['ratings']);

    $user_address_id = (int) $row['user_address_id'];
    $q5 = mysqli_query($conn, "SELECT * FROM user_address WHERE id = $user_address_id");
    $addressDetails = mysqli_fetch_assoc($q5);
    $user_address_details = array("location" => $addressDetails['location'], "address" => $addressDetails['address'], "latitude" => $addressDetails['latitude'], "longitude" => $addressDetails['longitude'], "zone_id" => (int) $addressDetails['zone_id']);


    $slot_id = (int) $row['slot_id'];
    $q6 = mysqli_query($conn, "SELECT name FROM slot WHERE id = $slot_id");
    $slotname = mysqli_fetch_assoc($q6);
    $slot = $slotname['name'];

    $updated = $row['updated'];

    $response['data'] = array(
        "status" => 1,
        "message" => "Subscription Term Details",
        "visit_id" => $visit_id,
        "term_name" => $term_name,
        "user_id" => $user_id,
        "otp" => $otp,
        "employee_details" => $employeeDetails == NULL ? array() : $employeeDetails,
        "main_category_id" => $main_category_id,
        "main_category_name" => $main_category_name,
        "main_category_id_chosen" => $main_category_id_chosen == NULL ? 0 : (int) $main_category_id_chosen,
        "main_category_chosen_name" => $main_category_chosen_name == NULL ? '' : $main_category_chosen_name,
        "device_serial_number" => $device_serial_number == NULL ? '' : $device_serial_number,
        "booking_id" => $booking_id,
        "rescheduled_count" => (int) $rescheduled_count,
        "booking_status" => $booking_status,
        "servicer_status" => $servicer_status,
        "booking_date" => $booking_date,
        "user_address_details" => $user_address_details,
        "slot" => $slot
    );
    echo json_encode($response);
    die();
}

function validateuserIdVisitId($conn, $user_id, $visit_id)
{
    $check = mysqli_query($conn, "SELECT b.user_id FROM booking b INNER JOIN booking_item_23 bi ON bi.booking_id = b.id WHERE b.user_id = $user_id AND bi.id=$visit_id");
    if (mysqli_num_rows($check) == 0) {
        $errorData["data"] = array("status" => 0,   "message" => "user_id and visit_id mismatch");
        echo json_encode($errorData);
        die();
    } else {
        return true;
    }
}


function customerRescheduleTerm()
{
    $conn = $GLOBALS['conn'];

    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['user_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No user_id is supplied");
        echo json_encode($errorData);

        die();
    }
    if (empty($input['user_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No user_id is supplied");
        echo json_encode($errorData);

        die();
    }
    $user_id = (int) $input['user_id'];
    validateUserId($conn, $user_id);
    //CHECK FOR BLOCKED CUSTOMER
    $checkk = mysqli_query($conn, "SELECT * FROM user WHERE id='$user_id' AND account_status IN('BLOCKED','INACTIVE')  ");
    if (mysqli_num_rows($checkk) > 0) {
        $errorData["data"] = array("status" => 0,   "message" => "This customer Account is Blocked or Inactive");
        echo json_encode($errorData);
        die();
    }
    //END oF BLOCKED

    if (!isset($input['visit_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No visit_id is supplied");
        echo json_encode($errorData);

        die();
    }
    if (empty($input['visit_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No visit_id is supplied");
        echo json_encode($errorData);
        die();
    }
    $visit_id = (int) $input['visit_id'];
    validateuserIdVisitId($conn, $user_id, $visit_id);


    if (!isset($input['slot_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No slot_id is supplied");
        echo json_encode($errorData);

        die();
    }
    if (empty($input['slot_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No slot_id is supplied");
        echo json_encode($errorData);

        die();
    }
    $slot_id = (int) $input['slot_id'];
    validateSlotId($conn, $slot_id);


    if (!isset($input['date'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No date is supplied");
        echo json_encode($errorData);

        die();
    }
    if (empty($input['date'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No date is supplied");
        echo json_encode($errorData);

        die();
    }
    $dateNew = $input['date'];
    date_default_timezone_set('Asia/Calcutta');
    $today = date('Y-m-d');
    if (strtotime($dateNew) < strtotime($today)) {
        $errorData["data"] = array("status" => 0,   "message" => "Given date is in past.");
        echo json_encode($errorData);
        die();
    }

    $timenow =  date('h:i:s');
    $tt = mysqli_query($conn, "SELECT start FROM slot WHERE id = $slot_id");
    $ch = mysqli_fetch_assoc($tt);
    $startNew = $ch['start'];
    $minutesLeftNew = strtotime($startNew) - strtotime($timenow);
    if ($minutesLeftNew < 0) {
        $errorData["data"] = array("status" => 0,   "message" => "Given date is today but time is in past. Invalid input");
        echo json_encode($errorData);
        die();
    }

    $q1 = mysqli_query($conn, "SELECT b.*, s.start FROM booking_item_23 b INNER JOIN slot s ON b.slot_id  = s.id WHERE b.id = $visit_id LIMIT 1");
    $bookingdetails = mysqli_fetch_assoc($q1);

    $rescheduled_count = (int) $bookingdetails['reschedule_count'];
    if ($rescheduled_count >= 2) {
        $errorData["data"] = array("status" => 0,   "message" => "Reschedule limit exceeded; cannot reschedule now");
        echo json_encode($errorData);
        die();
    }

    $status = $bookingdetails['status'];
    if ($status == 'CANCELLED') {
        $errorData["data"] = array("status" => 0,   "message" => "This booking already cancelled");
        echo json_encode($errorData);
        die();
    }
    ///////////////////////////////////
    $date = $bookingdetails['date'];
    date_default_timezone_set('Asia/Calcutta');
    $today = date('Y-m-d');
    if (strtotime($date) < strtotime($today)) {
        $errorData["data"] = array("status" => 0,   "message" => "Booked date is in past. Invalid input");
        echo json_encode($errorData);
        die();
    }

    $start = $bookingdetails['start'];
    date_default_timezone_set('Asia/Calcutta');
    $updated = date('Y-m-d');

    $timenow =  date('Y-m-d h:i:s');
    $booked_time = $date . " " . $start;
    $minutesLeft = CEIL((strtotime($booked_time) - strtotime($timenow)) / 60);

    if ($minutesLeft < 0) {
        $errorData["data"] = array("status" => 0,   "message" => "Booked time is in past. Invalid input");
        echo json_encode($errorData);
        die();
    } else if ($minutesLeft < 120) {
        $errorData["data"] = array("status" => 0,   "message" => "Booked time is less than 2 hours. Cannot reschedule this booking now");
        echo json_encode($errorData);
        die();
    }

    $user_address_id = (int) $bookingdetails['user_address_id'];
    $xyz = mysqli_query($conn, "SELECT zone_id FROM user_address WHERE id = $user_address_id");
    $bbc = mysqli_fetch_assoc($xyz);
    $zone_id = (int) $bbc['zone_id'];
    checkSlotAvailable($conn, $slot_id, $zone_id, $dateNew);

    if ($minutesLeft >= 120) {
        $reschedule = mysqli_query($conn, "UPDATE booking_item_23 SET reschedule_count = $rescheduled_count+1, status = 'RESCHEDULED', emp_id = NULL, date = '$dateNew', slot_id = $slot_id, updated = '$updated' WHERE id = $visit_id  ");
        if ($reschedule) {
            $errorData["data"] = array("status" => 1,   "message" => "Service term rescheduled successfully");
            echo json_encode($errorData);
            die();
        } else {
            $errorData["data"] = array("status" => 0,   "message" => "Internal server error");
            echo json_encode($errorData);
            die();
        }
    }
}

function CustomerCancelTerm()
{
    $conn = $GLOBALS['conn'];
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['user_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No user_id is supplied");
        echo json_encode($errorData);

        die();
    }
    if (empty($input['user_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No user_id is supplied");
        echo json_encode($errorData);

        die();
    }
    $user_id = (int) $input['user_id'];
    validateUserId($conn, $user_id);
    //CHECK FOR BLOCKED CUSTOMER
    $checkk = mysqli_query($conn, "SELECT * FROM user WHERE id='$user_id' AND account_status IN('BLOCKED','INACTIVE')  ");
    if (mysqli_num_rows($checkk) > 0) {
        $errorData["data"] = array("status" => 0,   "message" => "This customer Account is Blocked or Inactive");
        echo json_encode($errorData);
        die();
    }
    //END oF BLOCKED


    if (!isset($input['visit_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No visit_id is supplied");
        echo json_encode($errorData);

        die();
    }
    if (empty($input['visit_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No visit_id is supplied");
        echo json_encode($errorData);

        die();
    }
    $visit_id = (int) $input['visit_id'];
    validateuserIdVisitId($conn, $user_id, $visit_id);


    //////////////////////////////////////////////////////////////////////////////////
    $q1 = mysqli_query($conn, "SELECT b.*, s.start FROM booking_item_23 b INNER JOIN slot s ON b.slot_id  = s.id WHERE b.id = $visit_id LIMIT 1");
    $bookingdetails = mysqli_fetch_assoc($q1);

    $status = $bookingdetails['status'];
    if ($status == 'CANCELLED') {
        $errorData["data"] = array("status" => 0,   "message" => "This booking already cancelled");
        echo json_encode($errorData);
        die();
    }
    ///////////////////////////////////
    $date = $bookingdetails['date'];
    date_default_timezone_set('Asia/Calcutta');
    $today = date('Y-m-d');
    if (strtotime($date) < strtotime($today)) {
        $errorData["data"] = array("status" => 0,   "message" => "Booked date is in past. Invalid input");
        echo json_encode($errorData);
        die();
    }

    $start = $bookingdetails['start'];
    date_default_timezone_set('Asia/Calcutta');
    $updated = date('Y-m-d');

    $timenow =  date('Y-m-d h:i:s');

    $booked_time = $date . " " . $start;
    $minutesLeft = CEIL((strtotime($booked_time) - strtotime($timenow)) / 60);
    if ($minutesLeft < 0) {
        $errorData["data"] = array("status" => 0,   "message" => "Booked time is in past. Invalid input");
        echo json_encode($errorData);
        die();
    } else if ($minutesLeft < 120) {
        $errorData["data"] = array("status" => 0,   "message" => "Booked time is less than 2 hours. Cannot cancel this booking now");
        echo json_encode($errorData);
        die();
    }

    if ($minutesLeft >= 120) {
        $cancel = mysqli_query($conn, "UPDATE booking_item_23 SET status = 'CANCELLED', servicer_status='Cancelled', payment = 'CANCELLED', payment_type=NULL, updated = '$updated' WHERE id = $visit_id  ");
        if ($cancel) {
            $errorData["data"] = array("status" => 1,   "message" => "visit_id is cancelled by user successfully");
            echo json_encode($errorData);
            die();
        } else {
            $errorData["data"] = array("status" => 0,   "message" => "Internal server error");
            echo json_encode($errorData);
            die();
        }
    }
}


function rateCard()
{
    $conn = $GLOBALS['conn'];
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($_GET['sub_category_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No sub_category_id is supplied");
        echo json_encode($errorData);

        die();
    }
    if (empty($_GET['sub_category_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No sub_category_id is supplied");
        echo json_encode($errorData);

        die();
    }
    $sub_category_id = (int) $_GET['sub_category_id'];

    $q2 = mysqli_query($conn, "SELECT name FROM sub_categories WHERE id = $sub_category_id");
    if (mysqli_num_rows($q2) == 0) {
        $errorData["data"] = array("status" => 0,   "message" => "Invalid sub category ID or no spare part list found");
        echo json_encode($errorData);
        die();
    }
    $row2 = mysqli_fetch_assoc($q2);
    $subCategoryName = $row2['name'];

    $q1 = mysqli_query($conn, "SELECT * FROM rate_card WHERE sub_category_id = $sub_category_id ");
    if (mysqli_num_rows($q1) == 0) {
        $errorData["data"] = array("status" => 0,   "message" => "Invalid sub category ID or no spare part list found");
        echo json_encode($errorData);
        die();
    }
    while ($row = mysqli_fetch_assoc($q1)) {
        $rateCard[] = array(
            "sparePartId" => (int) $row['id'],
            "sparePartName" => $row['name'],
            "cost" => (int) $row['cost']
        );
    }
    $response['data'] = array(
        "status" => 1,
        "message" => "Spare part details",
        "subCategoryId" => $sub_category_id,
        "subCategoryName" => $subCategoryName,
        "rateCard" => $rateCard
    );
    echo json_encode($response);
    die();
}


function employeeOnGoing()
{
    $conn = $GLOBALS['conn'];

    if (!isset($_GET['emp_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No emp_id is supplied");
        echo json_encode($errorData);

        die();
    }
    if (empty($_GET['emp_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No emp_id is supplied");
        echo json_encode($errorData);

        die();
    }

    $emp_id = (int) $_GET['emp_id'];
    //CHECK FOR BLOCKED CUSTOMER
    $checkk = mysqli_query($conn, "SELECT * FROM employee WHERE id='$emp_id' AND account_status = 'BLOCKED' ");
    if (mysqli_num_rows($checkk) > 0) {
        $errorData["data"] = array("status" => 0,   "message" => "This employee Account is Blocked");
        echo json_encode($errorData);
        die();
    }
    //END oF BLOCKED
    checkEmployeeId($conn, $emp_id);

    date_default_timezone_set('Asia/Calcutta');
    $today_date =  date('Y-m-d');

    $datetime = new DateTime(date('Y-m-d'));
    $datetime->modify('+1 day');
    $tomorrow_date =  $datetime->format('Y-m-d');

    $datetime = new DateTime(date('Y-m-d'));
    $datetime->modify('+2 day');
    $dayaftertomorrow_date =  $datetime->format('Y-m-d');

    $q1 = mysqli_query($conn, "SELECT * FROM booking WHERE emp_id = $emp_id AND date IN ('$today_date','$tomorrow_date','$dayaftertomorrow_date') ORDER BY id DESC");
    $q2 = mysqli_query($conn, "SELECT * FROM booking_item_23 WHERE emp_id = $emp_id AND date IN ('$today_date','$tomorrow_date','$dayaftertomorrow_date') ORDER BY id DESC");

    if ((mysqli_num_rows($q1) == 0) && (mysqli_num_rows($q1) == 0)) {
        $all[] = array();
        $today[] = array();
        $tomorrow[] = array();
        $dayaftertomo[] = array();
    }
    else {
        ///////////////////////////NORMAL BOOKING//////////////////////////////////////////////////////////////
        while ($booking_row = mysqli_fetch_assoc($q1)) {
            $date = $booking_row['date'];
            if ($date == $today_date) {
                //////////////////////////////////////TODAY////////////////////////////////////////
                $user_id = (int) $booking_row['user_id'];
                $getname = mysqli_query($conn, "SELECT u.name, u.user_address_id, ua.address FROM user u INNER JOIN user_address ua ON u.id = ua.user_id WHERE u.id=$user_id AND ua.id = u.user_address_id LIMIT 1");
                $customerDetails = mysqli_fetch_assoc($getname);

                $slot_id = (int) $booking_row['slot_id'];
                $getslot = mysqli_query($conn, "SELECT name FROM slot WHERE id=$slot_id LIMIT 1");
                $slotDetails = mysqli_fetch_assoc($getslot);
                $slot_name = $slotDetails['name'];
                $time = substr($slot_name, 0, 8);

                $today[] = array(
                    "booking_id" => (int) $booking_row['id'],
                    "date" => $today_date,
                    "day" => "TODAY",
                    "servicer_status" => $booking_row['servicer_status'],
                    "credits" => (int) $booking_row['credits'],
                    "customerName" => $customerDetails['name'],
                    "customerAddress" => $customerDetails['address'],
                    "time" => $time,
                    "booking_type" => "Normal Booking"
                );
                $all[] = array(
                    "booking_id" => (int) $booking_row['id'],
                    "date" => $today_date,
                    "day" => "TODAY",
                    "servicer_status" => $booking_row['servicer_status'],
                    "credits" => (int) $booking_row['credits'],
                    "customerName" => $customerDetails['name'],
                    "customerAddress" => $customerDetails['address'],
                    "time" => $time,
                    "booking_type" => "Normal Booking"
                );
            } else  if ($date == $tomorrow_date) {
                //////////////////////////////////////TOMORROW////////////////////////////////////////
                $user_id = (int) $booking_row['user_id'];
                $getname = mysqli_query($conn, "SELECT u.name, u.user_address_id, ua.address FROM user u INNER JOIN user_address ua ON u.id = ua.user_id WHERE u.id=$user_id AND ua.id = u.user_address_id LIMIT 1");
                $customerDetails = mysqli_fetch_assoc($getname);

                $slot_id = (int) $booking_row['slot_id'];
                $getslot = mysqli_query($conn, "SELECT name FROM slot WHERE id=$slot_id LIMIT 1");
                $slotDetails = mysqli_fetch_assoc($getslot);
                $slot_name = $slotDetails['name'];
                $time = substr($slot_name, 0, 8);

                $tomorrow[] = array(
                    "booking_id" => (int) $booking_row['id'],
                    "date" => $tomorrow_date,
                    "day" => "TOMORROW",
                    "servicer_status" => $booking_row['servicer_status'],
                    "credits" => (int) $booking_row['credits'],
                    "customerName" => $customerDetails['name'],
                    "customerAddress" => $customerDetails['address'],
                    "time" => $time,
                    "booking_type" => "Normal Booking"
                );
                $all[] = array(
                    "booking_id" => (int) $booking_row['id'],
                    "date" => $tomorrow_date,
                    "day" => "TOMORROW",
                    "servicer_status" => $booking_row['servicer_status'],
                    "credits" => (int) $booking_row['credits'],
                    "customerName" => $customerDetails['name'],
                    "customerAddress" => $customerDetails['address'],
                    "time" => $time,
                    "booking_type" => "Normal Booking"
                );
            } else if ($date == $dayaftertomorrow_date) {
                //////////////////////////////////////DAy AFTER TOMORROW////////////////////////////////////////
                $user_id = (int) $booking_row['user_id'];
                $getname = mysqli_query($conn, "SELECT u.name, u.user_address_id, ua.address FROM user u INNER JOIN user_address ua ON u.id = ua.user_id WHERE u.id=$user_id AND ua.id = u.user_address_id LIMIT 1");
                $customerDetails = mysqli_fetch_assoc($getname);

                $slot_id = (int) $booking_row['slot_id'];
                $getslot = mysqli_query($conn, "SELECT name FROM slot WHERE id=$slot_id LIMIT 1");
                $slotDetails = mysqli_fetch_assoc($getslot);
                $slot_name = $slotDetails['name'];
                $time = substr($slot_name, 0, 8);

                $dayaftertomo[] = array(
                    "booking_id" => (int) $booking_row['id'],
                    "date" => $dayaftertomorrow_date,
                    "day" => "DAY AFTER TOMORROW",
                    "servicer_status" => $booking_row['servicer_status'],
                    "credits" => (int) $booking_row['credits'],
                    "customerName" => $customerDetails['name'],
                    "customerAddress" => $customerDetails['address'],
                    "time" => $time,
                    "booking_type" => "Normal Booking"
                );
                $all[] = array(
                    "booking_id" => (int) $booking_row['id'],
                    "date" => $dayaftertomorrow_date,
                    "day" => "DAY AFTER TOMORROW",
                    "servicer_status" => $booking_row['servicer_status'],
                    "credits" => (int) $booking_row['credits'],
                    "customerName" => $customerDetails['name'],
                    "customerAddress" => $customerDetails['address'],
                    "time" => $time,
                    "booking_type" => "Normal Booking"
                );
            }
        }

        /////////////////////////////////////////////SERVICE TERM BOOKINGS//////////////////////////////////////////
        ////////////////////////////////////////////////////////////////////////////////////////////
        while ($booking_row = mysqli_fetch_assoc($q2)) {
            $booking_id = (int) $booking_row['booking_id'];
            $date = $booking_row['date'];
            if ($date == $today_date) {
                //////////////////////////////////////TODAY////////////////////////////////////////
                $getuserid = mysqli_query($conn,"SELECT user_id FROM booking WHERE id = $booking_id ");
                $gg = mysqli_fetch_assoc($getuserid);
                $user_id = (int) $gg['user_id'];
                $getname = mysqli_query($conn, "SELECT u.name, u.user_address_id, ua.address FROM user u INNER JOIN user_address ua ON u.id = ua.user_id WHERE u.id=$user_id AND ua.id = u.user_address_id LIMIT 1");
                $customerDetails = mysqli_fetch_assoc($getname);

                $slot_id = (int) $booking_row['slot_id'];
                $getslot = mysqli_query($conn, "SELECT name FROM slot WHERE id=$slot_id LIMIT 1");
                $slotDetails = mysqli_fetch_assoc($getslot);
                $slot_name = $slotDetails['name'];
                $time = substr($slot_name, 0, 8);

                $today[] = array(
                    "booking_id" => $booking_id,
                    "visit_id"=>(int) $booking_row['id'],
                    "date" => $today_date,
                    "day" => "TODAY",
                    "servicer_status" => $booking_row['servicer_status'],
                    "credits" => (int) $booking_row['credits'],
                    "customerName" => $customerDetails['name'],
                    "customerAddress" => $customerDetails['address'],
                    "time" => $time,
                    "booking_type" => "Service Term Booking (From Type 2A and 2B)"
                );
                $all[] = array(
                    "booking_id" => $booking_id,
                    "visit_id" => (int) $booking_row['id'],
                    "date" => $today_date,
                    "day" => "TODAY",
                    "servicer_status" => $booking_row['servicer_status'],
                    "credits" => (int) $booking_row['credits'],
                    "customerName" => $customerDetails['name'],
                    "customerAddress" => $customerDetails['address'],
                    "time" => $time,
                    "booking_type" => "Service Term Booking (From Type 2A and 2B)"
                );
            } else  if ($date == $tomorrow_date) {
                //////////////////////////////////////TOMORROW////////////////////////////////////////
                $getuserid = mysqli_query($conn, "SELECT user_id FROM booking WHERE id = $booking_id ");
                $gg = mysqli_fetch_assoc($getuserid);
                $user_id = (int) $gg['user_id'];
                $getname = mysqli_query($conn, "SELECT u.name, u.user_address_id, ua.address FROM user u INNER JOIN user_address ua ON u.id = ua.user_id WHERE u.id=$user_id AND ua.id = u.user_address_id LIMIT 1");
                $customerDetails = mysqli_fetch_assoc($getname);

                $slot_id = (int) $booking_row['slot_id'];
                $getslot = mysqli_query($conn, "SELECT name FROM slot WHERE id=$slot_id LIMIT 1");
                $slotDetails = mysqli_fetch_assoc($getslot);
                $slot_name = $slotDetails['name'];
                $time = substr($slot_name, 0, 8);

                $tomorrow[] = array(
                    "booking_id" => $booking_id,
                    "visit_id" => (int) $booking_row['id'],
                    "date" => $tomorrow_date,
                    "day" => "TOMORROW",
                    "servicer_status" => $booking_row['servicer_status'],
                    "credits" => (int) $booking_row['credits'],
                    "customerName" => $customerDetails['name'],
                    "customerAddress" => $customerDetails['address'],
                    "time" => $time,
                    "booking_type" => "Service Term Booking (From Type 2A and 2B)"
                );
                $all[] = array(
                    "booking_id" => $booking_id,
                    "visit_id" => (int) $booking_row['id'],
                    "date" => $tomorrow_date,
                    "day" => "TOMORROW",
                    "servicer_status" => $booking_row['servicer_status'],
                    "credits" => (int) $booking_row['credits'],
                    "customerName" => $customerDetails['name'],
                    "customerAddress" => $customerDetails['address'],
                    "time" => $time,
                    "booking_type" => "Service Term Booking (From Type 2A and 2B)"
                );
            } else if ($date == $dayaftertomorrow_date) {
                //////////////////////////////////////DAy AFTER TOMORROW////////////////////////////////////////
                $getuserid = mysqli_query($conn, "SELECT user_id FROM booking WHERE id = $booking_id ");
                $gg = mysqli_fetch_assoc($getuserid);
                $user_id = (int) $gg['user_id'];
                $getname = mysqli_query($conn, "SELECT u.name, u.user_address_id, ua.address FROM user u INNER JOIN user_address ua ON u.id = ua.user_id WHERE u.id=$user_id AND ua.id = u.user_address_id LIMIT 1");
                $customerDetails = mysqli_fetch_assoc($getname);

                $slot_id = (int) $booking_row['slot_id'];
                $getslot = mysqli_query($conn, "SELECT name FROM slot WHERE id=$slot_id LIMIT 1");
                $slotDetails = mysqli_fetch_assoc($getslot);
                $slot_name = $slotDetails['name'];
                $time = substr($slot_name, 0, 8);

                $dayaftertomo[] = array(
                    "booking_id" => $booking_id,
                    "visit_id" => (int) $booking_row['id'],
                    "date" => $dayaftertomorrow_date,
                    "day" => "DAY AFTER TOMORROW",
                    "servicer_status" => $booking_row['servicer_status'],
                    "credits" => (int) $booking_row['credits'],
                    "customerName" => $customerDetails['name'],
                    "customerAddress" => $customerDetails['address'],
                    "time" => $time,
                    "booking_type" => "Service Term Booking (From Type 2A and 2B)"
                );
                $all[] = array(
                    "booking_id" => $booking_id,
                    "visit_id" => (int) $booking_row['id'],
                    "date" => $dayaftertomorrow_date,
                    "day" => "DAY AFTER TOMORROW",
                    "servicer_status" => $booking_row['servicer_status'],
                    "credits" => (int) $booking_row['credits'],
                    "customerName" => $customerDetails['name'],
                    "customerAddress" => $customerDetails['address'],
                    "time" => $time,
                    "booking_type" => "Service Term Booking (From Type 2A and 2B)"
                );
            }
        }
    }

    $response['data'] = array(
        "status" => 1,
        "message" => "Ongoing details for the employee : 1",
        "all" => $all,
        "today" => $today,
        "tomorrow" => $tomorrow,
        "dayAfterTomorrow" => $dayaftertomo
    );
    echo json_encode($response);
    die();
}

function viewEmployeeCalendarLeaves(){
    $conn = $GLOBALS['conn'];

        if (!isset($_GET['emp_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No emp_id is supplied");
        echo json_encode($errorData);

        die();
    }
    if (empty($_GET['emp_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No emp_id is supplied");
        echo json_encode($errorData);

        die();
    }


    $emp_id = (int) $_GET['emp_id'];
    //CHECK FOR BLOCKED CUSTOMER
    $checkk = mysqli_query($conn, "SELECT * FROM employee WHERE id='$emp_id' AND account_status = 'BLOCKED' ");
    if (mysqli_num_rows($checkk) > 0) {
        $errorData["data"] = array("status" => 0,   "message" => "This employee Account is Blocked");
        echo json_encode($errorData);
        die();
    }
    //END oF BLOCKED
    checkEmployeeId($conn, $emp_id);

    date_default_timezone_set('Asia/Calcutta');
    $today_date =  date('Y-m-d');

    $datetime = new DateTime(date('Y-m-d'));
    $datetime->modify('+1 day');
    $tomorrow_date =  $datetime->format('Y-m-d');

    $datetime = new DateTime(date('Y-m-d'));
    $datetime->modify('+2 day');
    $dayaftertomorrow_date =  $datetime->format('Y-m-d');

    $q1 = mysqli_query($conn,"SELECT a.*, s.name FROM available_slot a INNER JOIN slot s ON a.slot_id =s.id WHERE a.emp_id = $emp_id AND a.available = 'NO' AND a.date IN ('$today_date','$tomorrow_date','$dayaftertomorrow_date') ORDER BY a.slot_id");
    if(mysqli_num_rows($q1)==0){
        $errorData["data"] = array("status" => 0,   "message" => "No details available for current 3 days for this employee");
        echo json_encode($errorData);
        die();
    }

    while($row = mysqli_fetch_assoc($q1)){
        $date = $row['date'];
        if($date==$today_date){
            $today[] = array(
                "date"=> $today_date,
                "available"=>"NO",
                "time"=> $row['name']
            );
        }
        else if($date == $tomorrow_date){
            $tomorrow[] = array(
                "date" => $tomorrow_date,
                "available" => "NO",
                "time" => $row['name']
            );
        }
        else if($date == $dayaftertomorrow_date){
            $dayaftertomorrow[] = array(
                "date" => $dayaftertomorrow_date,
                "available" => "NO",
                "time" => $row['name']
            );
        }
    }
    $response['data'] = array(
        "status" => 1,
        "message" => "Calendar details for the employee : 1",
        "today" => $today==NULL?array(): $today,
        "tomorrow" => $tomorrow == NULL ? array() : $tomorrow,
        "dayAfterTomorrow" => $dayaftertomorrow == NULL ? array() : $dayaftertomorrow
    );
    echo json_encode($response);
    die();
}

function viewEmployeeCalendar(){
    $conn = $GLOBALS['conn'];

    if (!isset($_GET['emp_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No emp_id is supplied");
        echo json_encode($errorData);

        die();
    }
    if (empty($_GET['emp_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No emp_id is supplied");
        echo json_encode($errorData);

        die();
    }

    $emp_id = (int) $_GET['emp_id'];
    //CHECK FOR BLOCKED CUSTOMER
    $checkk = mysqli_query($conn, "SELECT * FROM employee WHERE id='$emp_id' AND account_status = 'BLOCKED' ");
    if (mysqli_num_rows($checkk) > 0) {
        $errorData["data"] = array("status" => 0,   "message" => "This employee Account is Blocked");
        echo json_encode($errorData);
        die();
    }
    //END oF BLOCKED
    checkEmployeeId($conn, $emp_id);

    date_default_timezone_set('Asia/Calcutta');
    $today_date =  date('Y-m-d');

    $datetime = new DateTime(date('Y-m-d'));
    $datetime->modify('+1 day');
    $tomorrow_date =  $datetime->format('Y-m-d');

    $datetime = new DateTime(date('Y-m-d'));
    $datetime->modify('+2 day');
    $dayaftertomorrow_date =  $datetime->format('Y-m-d');


    if (!isset($_GET['date'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No date is supplied");
        echo json_encode($errorData);

        die();
    }
    if (empty($_GET['date'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No date is supplied");
        echo json_encode($errorData);

        die();
    }
    $date = $_GET['date'];
    
    if(($date != $today_date)){
        if($date != $tomorrow_date){
            if($date != $dayaftertomorrow_date){
                $errorData["data"] = array("status" => 0,   "message" => "Invalid date; Only current 3 dates are accepted");
                echo json_encode($errorData);
                die();
            }
        }
    }

    $q1 = mysqli_query($conn, "SELECT a.available, s.id, s.name, s.period FROM available_slot a INNER JOIN slot s ON a.slot_id =s.id WHERE a.emp_id = $emp_id AND a.date = '$date' ORDER BY a.slot_id");
   
    if (mysqli_num_rows($q1) == 0) {
        $errorData["data"] = array("status" => 0,   "message" => "No details available for given date for this employee");
        echo json_encode($errorData);
        die();
    }

    while($row = mysqli_fetch_assoc($q1)){
        $slots[] = array(
            "slot_id"=>(int)$row['id'],
            "available" => $row['available'],
            "period" => $row['period'],
            "time"=> $row['name']
        );
    }   
    $response['data'] = array(
        "status"=> 1,
       "message"=> "Calendar details for the employee : 1",
       "date"=> $date,
       "slots" => $slots==NULL?array() : $slots
    );
    echo json_encode($response);
    die();
}

function banks(){
    $conn = $GLOBALS['conn'];
    $q1 = mysqli_query($conn,"SELECT * FROM banks ORDER BY name ASC");
    if(mysqli_num_rows($q1)==0){
        $errorData["data"] = array("status" => 0,   "message" => "No banks available");
        echo json_encode($errorData);
        die();
    }

    while($row = mysqli_fetch_assoc($q1))   {
        $banks[] = array(
            "bankId"=> (int) $row['id'],
            "bankName"=> $row['name']
        );
    }
    $response['data'] = array(
        "status"=> 1,
       "message"=> "Banks List",
       "banks"=>$banks
    );
    echo json_encode($response);
    die();
}

function employeeAccountDetails(){
    $conn = $GLOBALS['conn'];

    if (!isset($_GET['emp_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No emp_id is supplied");
        echo json_encode($errorData);

        die();
    }
    if (empty($_GET['emp_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No emp_id is supplied");
        echo json_encode($errorData);

        die();
    }

    $emp_id = (int) $_GET['emp_id'];
    //CHECK FOR BLOCKED CUSTOMER
    $checkk = mysqli_query($conn, "SELECT * FROM employee WHERE id='$emp_id' AND account_status = 'BLOCKED' ");
    if (mysqli_num_rows($checkk) > 0) {
        $errorData["data"] = array("status" => 0,   "message" => "This employee Account is Blocked");
        echo json_encode($errorData);
        die();
    }
    //END oF BLOCKED
    checkEmployeeId($conn, $emp_id);

    $q1 = mysqli_query($conn, "SELECT pan_number, bank_account_number, gst_number FROM employee_account_details WHERE emp_id = $emp_id LIMIT 1");

    if (mysqli_num_rows($q1) == 0) {
        $errorData["data"] = array("status" => 0,   "message" => "No banks available");
        echo json_encode($errorData);
        die();
    }

    $row = mysqli_fetch_assoc($q1);
    
    $response['data'] = array(
        "status"=> 1,
       "message"=> "Account details",
       "emp_id"=> $emp_id,
       "pan_number"=> $row['pan_number'],
       "bank_account_number"=> $row['bank_account_number'],
       "gst_number"=> $row['gst_number']
    );
    echo json_encode($response);
    die();
}

function employeeViewBankDetails(){
    $conn = $GLOBALS['conn'];

    if (!isset($_GET['emp_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No emp_id is supplied");
        echo json_encode($errorData);

        die();
    }
    if (empty($_GET['emp_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No emp_id is supplied");
        echo json_encode($errorData);

        die();
    }

    $emp_id = (int) $_GET['emp_id'];
    //CHECK FOR BLOCKED CUSTOMER
    $checkk = mysqli_query($conn, "SELECT * FROM employee WHERE id='$emp_id' AND account_status = 'BLOCKED' ");
    if (mysqli_num_rows($checkk) > 0) {
        $errorData["data"] = array("status" => 0,   "message" => "This employee Account is Blocked");
        echo json_encode($errorData);
        die();
    }
    //END oF BLOCKED
    checkEmployeeId($conn, $emp_id);

    $q1 = mysqli_query($conn, "SELECT * FROM employee_account_details WHERE emp_id = $emp_id LIMIT 1");
    $row = mysqli_fetch_assoc($q1);
    if (mysqli_num_rows($q1) == 0) {
        $errorData["data"] = array("status" => 0,   "message" => "No details available");
        echo json_encode($errorData);
        die();
    }
    $bank_id = (int) $row['bank_id'];
    $q2 = mysqli_query($conn, "SELECT name FROM banks WHERE id = $bank_id LIMIT 1");
    $row2 = mysqli_fetch_assoc($q2);
    $bank_name = $row2['name'];

    $response['data'] = array(
        "status" => 1,
        "message" => "Bank details",
        "emp_id" => $emp_id,
       "bank_id"=>(int)$row['bank_id'],
       "bank_name"=> $bank_name==NULL?'': $bank_name,
       "ifsc"=> $row['ifsc']==NULL?'': $row['ifsc'],
       "account_name"=> $row['bank_name']==NULL?: $row['bank_name'],
       "bank_account_number"=> $row['bank_account_number']==NULL?'': $row['bank_account_number'],
       "cheque_url"=> $row['cheque_url']==NULL?'': $row['cheque_url'],

    );
    echo json_encode($response);
    die();

}

function employeeViewPAN(){
    $conn = $GLOBALS['conn'];

    if (!isset($_GET['emp_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No emp_id is supplied");
        echo json_encode($errorData);

        die();
    }
    if (empty($_GET['emp_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No emp_id is supplied");
        echo json_encode($errorData);

        die();
    }

    $emp_id = (int) $_GET['emp_id'];
    //CHECK FOR BLOCKED CUSTOMER
    $checkk = mysqli_query($conn, "SELECT * FROM employee WHERE id='$emp_id' AND account_status = 'BLOCKED' ");
    if (mysqli_num_rows($checkk) > 0) {
        $errorData["data"] = array("status" => 0,   "message" => "This employee Account is Blocked");
        echo json_encode($errorData);
        die();
    }
    //END oF BLOCKED
    checkEmployeeId($conn, $emp_id);

    $q1 = mysqli_query($conn, "SELECT * FROM employee_account_details WHERE emp_id = $emp_id LIMIT 1");
    $row = mysqli_fetch_assoc($q1);
    if (mysqli_num_rows($q1) == 0) {
        $errorData["data"] = array("status" => 0,   "message" => "No details available");
        echo json_encode($errorData);
        die();
    }

    $q1 = mysqli_query($conn, "SELECT * FROM employee_account_details WHERE emp_id = $emp_id LIMIT 1");
    $row = mysqli_fetch_assoc($q1);
    if (mysqli_num_rows($q1) == 0) {
        $errorData["data"] = array("status" => 0,   "message" => "No details available");
        echo json_encode($errorData);
        die();
    }

    $response['data'] = array(
        "status" => 1,
        "message" => "PAN details",
        "emp_id" => $emp_id,
        "pan_name" => $row['pan_name'] == NULL ? '' : $row['pan_name'],
        "pan_number" => $row['pan_number'] == NULL ? '' : $row['pan_number'],
        "pan_front_url" => $row['pan_front_url'] == NULL ? '' : $row['pan_front_url'],

    );
    echo json_encode($response);
    die();

}

function employeeViewGST(){
    $conn = $GLOBALS['conn'];

    if (!isset($_GET['emp_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No emp_id is supplied");
        echo json_encode($errorData);

        die();
    }
    if (empty($_GET['emp_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No emp_id is supplied");
        echo json_encode($errorData);

        die();
    }

    $emp_id = (int) $_GET['emp_id'];
    //CHECK FOR BLOCKED CUSTOMER
    $checkk = mysqli_query($conn, "SELECT * FROM employee WHERE id='$emp_id' AND account_status = 'BLOCKED' ");
    if (mysqli_num_rows($checkk) > 0) {
        $errorData["data"] = array("status" => 0,   "message" => "This employee Account is Blocked");
        echo json_encode($errorData);
        die();
    }
    //END oF BLOCKED
    checkEmployeeId($conn, $emp_id);

    $q1 = mysqli_query($conn, "SELECT * FROM employee_account_details WHERE emp_id = $emp_id LIMIT 1");
    $row = mysqli_fetch_assoc($q1);
    if (mysqli_num_rows($q1) == 0) {
        $errorData["data"] = array("status" => 0,   "message" => "No details available");
        echo json_encode($errorData);
        die();
    }

    $q1 = mysqli_query($conn, "SELECT * FROM employee_account_details WHERE emp_id = $emp_id LIMIT 1");
    $row = mysqli_fetch_assoc($q1);
    if (mysqli_num_rows($q1) == 0) {
        $errorData["data"] = array("status" => 0,   "message" => "No details available");
        echo json_encode($errorData);
        die();
    }

    $response['data'] = array(
        "status" => 1,
        "message" => "GST details",
        "emp_id" => $emp_id,
        "gst_name" => $row['gst_name'] == NULL ? '' : $row['gst_name'],
        "gst_number" => $row['gst_number'] == NULL ? '' : $row['gst_number']

    );
    echo json_encode($response);
    die();

}

function employeeUpdateBankDetails(){
    $conn = $GLOBALS['conn'];
    $input = json_decode(file_get_contents('php://input'), true);


    if (!isset($_POST['emp_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No emp_id is supplied");
        echo json_encode($errorData);

        die();
    }
    if (empty($_POST['emp_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No emp_id is supplied");
        echo json_encode($errorData);

        die();
    }

    $emp_id = (int) $_POST['emp_id'];
    //CHECK FOR BLOCKED CUSTOMER
    $checkk = mysqli_query($conn, "SELECT * FROM employee WHERE id='$emp_id' AND account_status = 'BLOCKED' ");
    if (mysqli_num_rows($checkk) > 0) {
        $errorData["data"] = array("status" => 0,   "message" => "This employee Account is Blocked");
        echo json_encode($errorData);
        die();
    }
    //END oF BLOCKED
    checkEmployeeId($conn, $emp_id);


    $q1 = mysqli_query($conn, "SELECT * FROM employee_account_details WHERE emp_id = $emp_id LIMIT 1");

    ///////////////////////////////

    $types1 = array('image/jpeg', 'image/jpg', 'image/png');

    if (!in_array($_FILES['cheque_url']['type'], $types1)) {
            $response["data"] = array("status"=>0,"message"=>"JPG, JPEG, PNG formats alone accepted");
            echo json_encode($response);
            die();
    }

    //Uploading Images
    date_default_timezone_set('Asia/Calcutta');
    $datetime = date('dmY_hisA_');
    //image upload
    $file_name = "img_" . $datetime . ".jpg";
    $file_path = "images/" . $file_name;

    if ($_FILES["cheque_url"]["name"]) {
        $info = pathinfo($_FILES['cheque_url']['name']);
        move_uploaded_file($_FILES['cheque_url']['tmp_name'], $file_path);

        $server_name = "https://" . $_SERVER['SERVER_NAME'];
        $folder_name = dirname($_SERVER['PHP_SELF']) . "/images/";
        $cheque_url = $server_name . $folder_name . $file_name;
    }
    else {
        $cheque_url = '';
    }   
    
    ////////////////////////////
    $bank_id = (int)$_POST['bank_id'];

    $bankcheck = mysqli_query($conn, "SELECT name from banks WHERE id = $bank_id");
    if (mysqli_num_rows($bankcheck) == 0) {
        $errorData["data"] = array("status" => 0,   "message" => "Invalid bank_id");
        echo json_encode($errorData);
        die();
    }
    $bankrowname = mysqli_fetch_assoc($bankcheck);
    $bank_name = $bankrowname['name'];

    $account_name =  $_POST['account_name'];
    $bank_account_number =  $_POST['bank_account_number'];
    $ifsc = $_POST['ifsc'];
    $emp_id =  $_POST['emp_id'];


    if (mysqli_num_rows($q1) == 0) {
        $q1 = mysqli_query($conn,"INSERT INTO employee_account_details(cheque_url, bank_id, bank_name, bank_account_number, ifsc, emp_id)
                                    VALUES ('$cheque_url', $bank_id, '$account_name', '$bank_account_number', '$ifsc', $emp_id)");
        if($q1){
            $response['data'] = array(
                "status"=> 1,
                "message"=> "Bank details updated",
                "emp_id"=> $emp_id,
                "bank_id"=> $bank_id,
                "bank_name"=> $bank_name,
                "ifsc"=> $ifsc,
                "account_name"=> $account_name,
                "bank_account_number"=> $bank_account_number,
                "cheque_url"=> $cheque_url
            );
            echo json_encode($response);
            die();
        }
    }
    else {
        $q1 = mysqli_query($conn, "UPDATE employee_account_details SET cheque_url = '$cheque_url',
                                                                        bank_id = $bank_id,
                                                                        bank_name = '$account_name',
                                                                        bank_account_number = '$bank_account_number',
                                                                        ifsc = '$ifsc'
                                                                        WHERE emp_id = $emp_id ");
        if ($q1) {
            $response['data'] = array(
                "status" => 1,
                "message" => "Bank details updated",
                "emp_id" => $emp_id,
                "bank_id" => $bank_id,
                "bank_name" => $bank_name,
                "ifsc" => $ifsc,
                "account_name" => $account_name,
                "bank_account_number" => $bank_account_number,
                "cheque_url" => $cheque_url
            );
            echo json_encode($response);
            die();
        }
    }
}

function employeeUpdatePAN()
{
    $conn = $GLOBALS['conn'];
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($_POST['emp_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No emp_id is supplied");
        echo json_encode($errorData);

        die();
    }
    if (empty($_POST['emp_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No emp_id is supplied");
        echo json_encode($errorData);

        die();
    }

    $emp_id = (int) $_POST['emp_id'];
    //CHECK FOR BLOCKED CUSTOMER
    $checkk = mysqli_query($conn, "SELECT * FROM employee WHERE id='$emp_id' AND account_status = 'BLOCKED' ");
    if (mysqli_num_rows($checkk) > 0) {
        $errorData["data"] = array("status" => 0,   "message" => "This employee Account is Blocked");
        echo json_encode($errorData);
        die();
    }
    //END oF BLOCKED
    checkEmployeeId($conn, $emp_id);


    $q1 = mysqli_query($conn, "SELECT * FROM employee_account_details WHERE emp_id = $emp_id LIMIT 1");

    ///////////////////////////////

    $types1 = array('image/jpeg', 'image/jpg', 'image/png');

    if (!in_array($_FILES['pan_front_url']['type'], $types1)) {
        $response["data"] = array("status" => 0, "message" => "JPG, JPEG, PNG formats alone accepted");
        echo json_encode($response);
        die();
    }

    //Uploading Images
    date_default_timezone_set('Asia/Calcutta');
    $datetime = date('dmY_hisA_');
    //image upload
    $file_name = "img_" . $datetime . ".jpg";
    $file_path = "images/" . $file_name;

    if ($_FILES["pan_front_url"]["name"]) {
        $info = pathinfo($_FILES['pan_front_url']['name']);
        move_uploaded_file($_FILES['pan_front_url']['tmp_name'], $file_path);

        $server_name = "https://" . $_SERVER['SERVER_NAME'];
        $folder_name = dirname($_SERVER['PHP_SELF']) . "/images/";
        $pan_front_url = $server_name . $folder_name . $file_name;
    } else {
        $pan_front_url = '';
    }

    ////////////////////////////
    $pan_name =  $_POST['pan_name'];
    $pan_number =  $_POST['pan_number'];
   
    if (mysqli_num_rows($q1) == 0) {
        $q1 = mysqli_query($conn, "INSERT INTO employee_account_details(pan_front_url, pan_name, pan_number, emp_id)
                                    VALUES ('$pan_front_url','$pan_name', '$pan_number', $emp_id)");
        if ($q1) {
            $response['data'] = array(
                "status" => 1,
                "message" => "PAN details updated",
                "emp_id" => $emp_id,
                "pan_name" => $pan_name,
                "pan_number" => $pan_number,
                "pan_front_url" => $pan_front_url
            );
            echo json_encode($response);
            die();
        }
    } else {
        $q1 = mysqli_query($conn, "UPDATE employee_account_details SET pan_front_url = '$pan_front_url',
                                                                        pan_name = '$pan_name',
                                                                        pan_number = '$pan_number'
                                                                        WHERE emp_id = $emp_id ");
        if ($q1) {
            $response['data'] = array(
                "status" => 1,
                "message" => "PAN details updated",
                "emp_id" => $emp_id,
                "pan_name" => $pan_name,
                "pan_number" => $pan_number,
                "pan_front_url" => $pan_front_url
            );
            echo json_encode($response);
            die();
        }
    }
}

function employeeUpdateGST(){
    $conn = $GLOBALS['conn'];
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['emp_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No emp_id is supplied");
        echo json_encode($errorData);

        die();
    }
    if (empty($input['emp_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No emp_id is supplied");
        echo json_encode($errorData);

        die();
    }

    $emp_id = (int) $input['emp_id'];
    //CHECK FOR BLOCKED CUSTOMER
    $checkk = mysqli_query($conn, "SELECT * FROM employee WHERE id='$emp_id' AND account_status = 'BLOCKED' ");
    if (mysqli_num_rows($checkk) > 0) {
        $errorData["data"] = array("status" => 0,   "message" => "This employee Account is Blocked");
        echo json_encode($errorData);
        die();
    }
    //END oF BLOCKED
    checkEmployeeId($conn, $emp_id);


    $q1 = mysqli_query($conn, "SELECT * FROM employee_account_details WHERE emp_id = $emp_id LIMIT 1");

    ///////////////////////////////

    $gst_name =  $input['gst_name'];
    $gst_number =  $input['gst_number'];

    if (mysqli_num_rows($q1) == 0) {
        $q1 = mysqli_query($conn, "INSERT INTO employee_account_details(gst_name, gst_number, emp_id)
                                    VALUES ('$gst_name', '$gst_number', $emp_id)");
        if ($q1) {
            $response['data'] = array(
                "status" => 1,
                "message" => "GST details updated",
                "emp_id" => $emp_id,
                "gst_name" => $gst_name,
                "gst_number" => $gst_number
            );
            echo json_encode($response);
            die();
        }
    } else {
        $q1 = mysqli_query($conn, "UPDATE employee_account_details SET gst_name = '$gst_name',
                                                                        gst_number = '$gst_number'
                                                                        WHERE emp_id = $emp_id ");
        if ($q1) {
            $response['data'] = array(
                "status" => 1,
                "message" => "GST details updated",
                "emp_id" => $emp_id,
                "gst_name" => $gst_name,
                "gst_number" => $gst_number
            );
            echo json_encode($response);
            die();
        }
    }
}

function servicerUpdateLeave(){
    $conn = $GLOBALS['conn'];
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['emp_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No emp_id is supplied");
        echo json_encode($errorData);

        die();
    }
    if (empty($input['emp_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No emp_id is supplied");
        echo json_encode($errorData);

        die();
    }

    $emp_id = (int) $input['emp_id'];
    //CHECK FOR BLOCKED CUSTOMER
    $checkk = mysqli_query($conn, "SELECT * FROM employee WHERE id='$emp_id' AND account_status = 'BLOCKED' ");
    if (mysqli_num_rows($checkk) > 0) {
        $errorData["data"] = array("status" => 0,   "message" => "This employee Account is Blocked");
        echo json_encode($errorData);
        die();
    }
    //END oF BLOCKED
    checkEmployeeId($conn, $emp_id);

    date_default_timezone_set('Asia/Calcutta');
    $today_date =  date('Y-m-d');

    $datetime = new DateTime(date('Y-m-d'));
    $datetime->modify('+1 day');
    $tomorrow_date =  $datetime->format('Y-m-d');

    $datetime = new DateTime(date('Y-m-d'));
    $datetime->modify('+2 day');
    $dayaftertomorrow_date =  $datetime->format('Y-m-d');

    $date = $input['date'];

    if (($date != $today_date)) {
        if ($date != $tomorrow_date) {
            if ($date != $dayaftertomorrow_date) {
                $errorData["data"] = array("status" => 0,   "message" => "Invalid date; Only current 3 dates are accepted");
                echo json_encode($errorData);
                die();
            }
        }
    }


    if (!isset($input['yes'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No yes is supplied");
        echo json_encode($errorData);

        die();
    }
    if (empty($input['yes'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No yes is supplied");
        echo json_encode($errorData);

        die();
    }
    $yes_input = trim($input['yes']);
    $yes_arr = explode(",", $yes_input);

    if(count($yes_arr) != count(array_unique($yes_arr))){
        $errorData["data"] = array("status" => 0,   "message" => "yes - has duplicate values");
        echo json_encode($errorData);
        die();
    }

    if (!isset($input['no'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No no is supplied");
        echo json_encode($errorData);

        die();
    }
    if (empty($input['no'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No no is supplied");
        echo json_encode($errorData);

        die();
    }
    $no_input = trim($input['no']);
    $no_arr = explode(",", $no_input);
    if (count($no_arr) != count(array_unique($no_arr))) {
        $errorData["data"] = array("status" => 0,   "message" => "no - has duplicate values");
        echo json_encode($errorData);
        die();
    }

    foreach ($yes_arr as $num) {
        if (in_array($num, $no_arr)) {
            $errorData["data"] = array("status" => 0,   "message" => "yes and no - has duplicate values");
            echo json_encode($errorData);
            die();
        }
    }

    foreach ($no_arr as $num) {
        if (in_array($num, $yes_arr)) {
            $errorData["data"] = array("status" => 0,   "message" => "yes and no - has duplicate values");
            echo json_encode($errorData);
            die();
        }
    }

    $total_slot_count = count($yes_arr)+ count($no_arr);

    $slotchecks = mysqli_query($conn, "SELECT COUNT(id) AS count FROM slot");
    $zzz = mysqli_fetch_assoc($slotchecks);
    $actual_count = (int)$zzz['count'];

    if($total_slot_count != $actual_count){
        $errorData["data"] = array("status" => 0,   "message" => "Total number of slots count should be : $actual_count");
        echo json_encode($errorData);
        die();
    }

    $getzone = mysqli_query($conn, "SELECT zone_id FROM employee WHERE id = $emp_id");
    if (mysqli_num_rows($getzone) == 0) {
        $errorData["data"] = array("status" => 0,   "message" => "This employee has no zone_id selected; So could not update slot details");
        echo json_encode($errorData);
        die();
    }

    $abc = mysqli_fetch_assoc($getzone);
    $zone_id = (int)$abc['zone_id'];
    $i=0;
    $params = '';
    while($i< count($yes_arr)){
        $params .= "(".$emp_id.",".$zone_id.",'".$date."',".$yes_arr[$i].",'YES'),";
        $i++;
    }
    $i = 0;
    while ($i < count($no_arr)) {
        $params .= "(" . $emp_id . "," . $zone_id . ",'" . $date . "'," . $no_arr[$i] . ",'NO'),";
        $i++;
    }
    $params = rtrim($params, ',');

    $del = mysqli_query($conn, "DELETE FROM available_slot WHERE emp_id = $emp_id AND date = '$date' ");
    
    $insert = mysqli_query($conn, "INSERT INTO available_slot(emp_id, zone_id, date, slot_id, available) VALUES $params");
    if($insert){
        $errorData["data"] = array("status" => 1,   "message" => "available slots updated for employee ID: $emp_id for date : $date ");
        echo json_encode($errorData);
        die();
    }
    else{
        $errorData["data"] = array("status" => 0,   "message" => "internal server error");
        echo json_encode($errorData);
        die();
    }
}


function employeeJobHistory(){
    $conn = $GLOBALS['conn'];
    if (!isset($_GET['emp_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No emp_id is supplied");
        echo json_encode($errorData);

        die();
    }
    if (empty($_GET['emp_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No emp_id is supplied");
        echo json_encode($errorData);

        die();
    }

    $emp_id = (int) $_GET['emp_id'];
    //CHECK FOR BLOCKED CUSTOMER
    $checkk = mysqli_query($conn, "SELECT * FROM employee WHERE id='$emp_id' AND account_status = 'BLOCKED' ");
    if (mysqli_num_rows($checkk) > 0) {
        $errorData["data"] = array("status" => 0,   "message" => "This employee Account is Blocked");
        echo json_encode($errorData);
        die();
    }
    //END oF BLOCKED
    checkEmployeeId($conn, $emp_id);

    $q1 = mysqli_query($conn, "SELECT b.id  as booking_id, b.status, b.payment_type, b.user_address_id, ua.address, u.id, u.name FROM `booking` b
                                    INNER JOIN user u  ON u.id = b.user_id
                                    INNER JOIN user_address ua ON ua.id = b.user_address_id
                                    WHERE emp_id=$emp_id");
    if (mysqli_num_rows($q1) == 0) {
        $jobHistory1 = array();
    }    
    while($row = mysqli_fetch_assoc($q1)){
        $jobHistory1[] = array(
                "type"=>"Normal Booking",
                "bookingId"=>(int)$row['booking_id'],
                "booking_status"=>$row['status'],
                "customer_name"=>$row['name'],
                "customer_address"=>$row['address'],
                "payment_type"=>$row['payment_type']==NULL?'':$row['payment_type']
            );
    }
    
    $q2 = mysqli_query($conn, "SELECT b.id as visit_id, bb.id as booking_id, b.status, ua.address, u.name FROM `booking_item_23` b
									INNER JOIN booking bb ON b.booking_id = bb.id
                                    INNER JOIN user u  ON u.id = bb.user_id
                                    INNER JOIN user_address ua ON ua.id = b.user_address_id
                                    WHERE b.emp_id=$emp_id");
                                    
    if (mysqli_num_rows($q2)==0) {
        $jobHistory2 = array();
    }    
    while($row2 = mysqli_fetch_assoc($q2)){
        $jobHistory2[] = array(
                "type"=>"Service Term Booking",
                "bookingId"=>(int)$row2['booking_id'],
                "visit_id"=>(int)$row2['visit_id'],
                "booking_status"=>$row2['status'],
                "customer_name"=>$row2['name'],
                "customer_address"=>$row2['address'],
                "payment_type"=>$row['payment_type']==NULL?'':$row['payment_type']
            );
    }
    
    $response['data'] = array("status"=>1, "message"=>"Job history for emp_id : $emp_id", "job_history"=>array("normal_booking"=>$jobHistory1, "service_term_booking"=>$jobHistory2));
    echo json_encode($response);
    die();
    


}


function servicerViewBooking(){
    $conn = $GLOBALS['conn'];
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($_GET['booking_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No booking_id is supplied");
        echo json_encode($errorData);

        die();
    }
    if (empty($_GET['booking_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No booking_id is supplied");
        echo json_encode($errorData);
        die();
    }
    $booking_id = (int) $_GET['booking_id'];

    $q1 = mysqli_query($conn, "SELECT * FROM booking WHERE id = $booking_id LIMIT 1");
    if (mysqli_num_rows($q1)==0) {
        $errorData["data"] = array("status" => 0,   "message" => "No details found");
        echo json_encode($errorData);
        die();
    }
    $bookingdetails = mysqli_fetch_assoc($q1);
    $otp = (int) $bookingdetails['otp'];
    
    $rescheduled_count = (int) $bookingdetails['rescheduled_count'];
    $servicer_reschedule_count = (int) $bookingdetails['servicer_reschedule_count'];
    $booking_status =  $bookingdetails['status'];
    $servicer_status =  $bookingdetails['servicer_status'];
    $servicer_comment =  $bookingdetails['servicer_comment'];
    $payment_type =  $bookingdetails['payment_type'];
    $payment =  $bookingdetails['payment'];
    $booking_date =  $bookingdetails['date'];
    $estimate =  (int) $bookingdetails['estimate'];
    $tax_percent =  (int) $bookingdetails['tax_percent'];
    $tax_amount =  (int) $bookingdetails['tax_amount'];
    $total =  (int) $bookingdetails['total'];
    $coupon =   $bookingdetails['coupon'];

    $q4 = mysqli_query($conn, "SELECT description FROM coupon WHERE code = '$coupon' ");
    $coupondesc = mysqli_fetch_assoc($q4);
    $coupon_description =   $coupondesc['description'];

    $coupon_reduce =  (int) $bookingdetails['coupon_reduce'];
    $wallet_reduce =  (int) $bookingdetails['wallet_reduce'];
    $amount_payable =  (int) $bookingdetails['amount_payable'];

    $user_id = (int) $bookingdetails['user_id'];
    $namecheck = mysqli_query($conn,"SELECT name FROM user WHERE id = $user_id");
    $thiss = mysqli_fetch_assoc($namecheck);
    $customer_name = $thiss['name'];
    $user_address_id =  (int) $bookingdetails['user_address_id'];
    $q5 = mysqli_query($conn, "SELECT * FROM user_address WHERE id = $user_address_id");
    $addressDetails = mysqli_fetch_assoc($q5);

    $user_address_details = array("location" => $addressDetails['location'], "address" => $addressDetails['address'], "latitude" => $addressDetails['latitude'], "longitude" => $addressDetails['longitude'], "zone_id" => (int) $addressDetails['zone_id']);

    $slot_id =  (int) $bookingdetails['slot_id'];
    $q6 = mysqli_query($conn, "SELECT name FROM slot WHERE id = $slot_id");
    $slotname = mysqli_fetch_assoc($q6);
    $slot = $slotname['name'];

    $updated = $bookingdetails['updated'];

    $q7 = mysqli_query($conn, "SELECT bi.*, product_title, product_description, mc.name FROM booking_item bi INNER JOIN products p ON bi.product_id = p.id INNER JOIN main_categories mc ON p.category_id = mc.id WHERE booking_id = $booking_id");;
    while ($bookingItem = mysqli_fetch_assoc($q7)) {
        $bookingItemsArray[] = array(
            "bookingItemId" => (int) $bookingItem['id'],
            "productId" => (int) $bookingItem['product_id'],
            "productTitle" =>  $bookingItem['product_title'],
            "productDescription" =>  $bookingItem['product_description'],
            "mainCategoryName" => $bookingItem['name'],
            "count" => (int) $bookingItem['count'],
            "price" => (int) $bookingItem['price']
        );
    }

    $response['data'] = array(
        "status" => 1,
        "message" => "Booking Detail",
        "otp" => (int) $otp,
        "booking_id" => (int) $booking_id,
        "rescheduled_count" => (int) $rescheduled_count,
        "servicer_reschedule_count" => (int) $servicer_reschedule_count,
        "booking_status" => $booking_status,
        "servicer_status" => $servicer_status,
        "servicer_comment" => $servicer_comment,
        "payment_type" => $payment_type,
        "payment" => $payment,
        "booking_date" =>  $booking_date,
        "estimate" => (int) $estimate,
        "tax_percent" => (int) $tax_percent,
        "tax_amount" => (int) $tax_amount,
        "total" => (int) $total,
        "coupon" => $coupon,
        "coupon_description" => $coupon_description,
        "coupon_reduce" => (int) $coupon_reduce,
        "wallet_reduce" => (int) $wallet_reduce,
        "amount_payable" => (int) $amount_payable,
        "customer_name"=>$customer_name,
        "customer_address_details" => $user_address_details,
        "slot" => $slot,
        "updated" => $updated,
        "booking_items" => $bookingItemsArray
    );
    echo json_encode($response);
    die();
}


function servicerViewSingleTerm(){
    
    $conn = $GLOBALS['conn'];
    $input = json_decode(file_get_contents('php://input'), true);

    
    if (!isset($_GET['visit_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No visit_id is supplied");
        echo json_encode($errorData);

        die();
    }
    if (empty($_GET['visit_id'])) {
        $errorData["data"] = array("status" => 0,   "message" => "No visit_id is supplied");
        echo json_encode($errorData);
        die();
    }
    $visit_id = (int) $_GET['visit_id'];
    

    $q1 = mysqli_query($conn, "SELECT mc.name,
                (SELECT name FROM main_categories mcc WHERE mcc.id = bi.main_category_id_chosen)AS main_category_chosen_name,
                bi.* FROM booking_item_23 bi INNER JOIN main_categories mc ON mc.id = bi.`main_category_id` WHERE bi.id = $visit_id");
    $row = mysqli_fetch_assoc($q1);

    $term_name = "Service Term - " . $row['visit_number'];
    $otp = (int) $row['otp'];
    $main_category_id = (int) $row['main_category_id'];
    $main_category_name = $row['name'];
    $main_category_id_chosen = $row['main_category_id_chosen'];
    $main_category_chosen_name = $row['main_category_chosen_name'];
    $device_serial_number = $row['device_serial_number'];
    $booking_id = (int) $row['booking_id'];
    $rescheduled_count = (int) $row['reschedule_count'];
    $servicer_reschedule_count = (int) $row['servicer_reschedule_count'];
    $booking_status = $row['status'];
    $servicer_status = $row['servicer_status'];
    $booking_date = $row['date'];

    $getname = mysqli_query($conn,"SELECT u.name FROM user u INNER JOIN booking b ON b.user_id = u.id INNER JOIN booking_item_23 bi ON bi.booking_id = b.id WHERE bi.id=$visit_id");
    $zzz = mysqli_fetch_assoc($getname);
    $customer_name = $zzz['name'];
    $user_address_id = (int) $row['user_address_id'];
    $q5 = mysqli_query($conn, "SELECT * FROM user_address WHERE id = $user_address_id");
    $addressDetails = mysqli_fetch_assoc($q5);
    $user_address_details = array("location" => $addressDetails['location'], "address" => $addressDetails['address'], "latitude" => $addressDetails['latitude'], "longitude" => $addressDetails['longitude'], "zone_id" => (int) $addressDetails['zone_id']);


    $slot_id = (int) $row['slot_id'];
    $q6 = mysqli_query($conn, "SELECT name FROM slot WHERE id = $slot_id");
    $slotname = mysqli_fetch_assoc($q6);
    $slot = $slotname['name'];

    $updated = $row['updated'];

    $response['data'] = array(
        "status" => 1,
        "message" => "Subscription Term Details",
        "visit_id" => $visit_id,
        "term_name" => $term_name,
        "user_id" => $user_id,
        "otp" => $otp,
        "employee_details" => $employeeDetails == NULL ? array() : $employeeDetails,
        "main_category_id" => $main_category_id,
        "main_category_name" => $main_category_name,
        "main_category_id_chosen" => $main_category_id_chosen == NULL ? 0 : (int) $main_category_id_chosen,
        "main_category_chosen_name" => $main_category_chosen_name == NULL ? '' : $main_category_chosen_name,
        "device_serial_number" => $device_serial_number == NULL ? '' : $device_serial_number,
        "booking_id" => $booking_id,
        "rescheduled_count" => (int) $rescheduled_count,
        "booking_status" => $booking_status,
        "servicer_status" => $servicer_status,
        "booking_date" => $booking_date,
        "customer_name"=>$customer_name,
        "customer_address_details" => $user_address_details,
        "slot" => $slot
    );
    echo json_encode($response);
    die();
}