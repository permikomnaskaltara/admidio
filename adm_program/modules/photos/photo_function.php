<?php
   /******************************************************************************
 * Photofunktionen
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Jochen Erkens
 *
 * Uebergaben:
 *
 * pho_id: id der Veranstaltung
 * job: - do_delete
 *      - rotate
 *      - delete_request
 * direction: drehrichtung links oder rechts
 * bild: Nr. des Bildes welches verarbeitet werden soll
 * thumb_seite: von welcher Thumnailseite aus wurde die Funktion aufgerufen
 ******************************************************************************
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 *
 *****************************************************************************/
require_once("../../system/common.php");
require_once("../../system/login_valid.php");

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($g_preferences['enable_photo_module'] != 1)
{
    // das Modul ist deaktiviert
    $g_message->show("module_disabled");
}

// erst pruefen, ob der User Fotoberarbeitungsrechte hat
if(!$g_current_user->editPhotoRight())
{
    $g_message->show("photoverwaltunsrecht");
}

//URL auf Navigationstack ablegen
$_SESSION['navigation']->addUrl($g_current_url);

// Uebergabevariablen pruefen

//ID Pruefen
if(isset($_GET["pho_id"]) && is_numeric($_GET["pho_id"]))
{
    $pho_id = $_GET["pho_id"];
}
else 
{
    $pho_id = NULL;
}

if(isset($_GET["job"]) && $_GET["job"] != "rotate" && $_GET["job"] != "delete_request" && $_GET["job"] != "do_delete")
{
    $g_message->show("invalid");
}

if(isset($_GET["direction"]) && $_GET["direction"] != "left" && $_GET["direction"] != "right")
{
    $g_message->show("invalid");
}

if(isset($_GET["job"]) && (isset($_GET["bild"]) == false || is_numeric($_GET["bild"]) == false) )
{
    $g_message->show("invalid");
}

//Funktion zum Speichern von Bildern
//Kind (upload, thumb)

function image_save($orig_path, $scale, $destination_path)
{
    if(file_exists($orig_path))
    {
        //Ermittlung der Original Bildgroesse
        $bildgroesse = getimagesize($orig_path);

        //Errechnung seitenverhaeltniss
        $seitenverhaeltnis = $bildgroesse[0]/$bildgroesse[1];

        //laengere seite soll skalliert werden
        //Errechnug neuen Bildgroesse Querformat
        if($bildgroesse[0]>=$bildgroesse[1])
        {
            $neubildsize = array ($scale, round($scale/$seitenverhaeltnis));
        }
        //Errechnug neuen Bildgroesse Hochformat
        if($bildgroesse[0]<$bildgroesse[1]){
            $neubildsize = array (round($scale*$seitenverhaeltnis), $scale);
        }


        // Erzeugung neues Bild
        $neubild = imagecreatetruecolor($neubildsize[0], $neubildsize[1]);

        //Aufrufen des Originalbildes
        $bilddaten = imagecreatefromjpeg($orig_path);

        //kopieren der Daten in neues Bild
        imagecopyresampled($neubild, $bilddaten, 0, 0, 0, 0, $neubildsize[0], $neubildsize[1], $bildgroesse[0], $bildgroesse[1]);

        //falls Bild existiert: Loeschen
        if(file_exists($destination_path)){
            unlink($destination_path);
        }

        //Bild in Zielordner abspeichern
        imagejpeg($neubild, $destination_path, 90);
        chmod($destination_path,0777);

        imagedestroy($neubild);
    }    
}




//Rechtsdrehung eines Bildes
//pho_id: Veranstaltungsid
//bild: nr des Bildes das gedreht werden soll
function right_rotate ($pho_id, $bild)
{
    global $g_adm_con;
    header("Content-Type: image/jpeg");

    //Aufruf der ggf. Uebergebenen Veranstaltung
    $sql = "    SELECT *
                FROM ". TBL_PHOTOS. "
                WHERE (pho_id ='$pho_id')";
    $result_event = mysql_query($sql, $g_adm_con);
    db_error($result_event,__FILE__,__LINE__);
    $adm_photo = mysql_fetch_array($result_event);

    //Ordnerpfad zusammensetzen
    $ordner = "../../../adm_my_files/photos/".$adm_photo["pho_begin"]."_".$adm_photo["pho_id"];

    //Ermittlung der Original Bildgroessee
    $bildgroesse = getimagesize("$ordner/$bild.jpg");

    // Erzeugung neues Bild
    $neubild = imagecreatetruecolor($bildgroesse[1], $bildgroesse[0]);

    //Aufrufen des Originalbildes
    $bilddaten = imagecreatefromjpeg("$ordner/$bild.jpg");

    //kopieren der Daten in neues Bild
    for($y=0; $y<$bildgroesse[1]; $y++)
    {
        for($x=0; $x<$bildgroesse[0]; $x++)
        {
            imagecopy($neubild, $bilddaten, $bildgroesse[1]-$y-1, $x, $x, $y, 1,1 );
        }
    }

    //ursprungsdatei loeschen
    if(file_exists("$ordner/$bild.jpg"))
    {
        chmod("$ordner/$bild.jpg", 0777);
        unlink("$ordner/$bild.jpg");
    }

    //speichern
    imagejpeg($neubild, "$ordner/$bild.jpg", 90);
    chmod("$ordner/$bild.jpg",0777);

    //Loeschen des Bildes aus Arbeitsspeicher
    imagedestroy($neubild);
    imagedestroy($bilddaten);
};

//Linksdrehung eines Bildes
//pho_id: Veranstaltungsid
//bild: nr des Bildes das gedreht werden soll
function left_rotate ($pho_id, $bild)
{
    global $g_adm_con;
    header("Content-Type: image/jpeg");

    //Aufruf der ggf. Uebergebenen Veranstaltung
    $sql = "    SELECT *
                FROM ". TBL_PHOTOS. "
                WHERE (pho_id ='$pho_id')";
    $result_event = mysql_query($sql, $g_adm_con);
    db_error($result_event,__FILE__,__LINE__);
    $adm_photo = mysql_fetch_array($result_event);

    //Ordnerpfad zusammensetzen
    $ordner = "../../../adm_my_files/photos/".$adm_photo["pho_begin"]."_".$adm_photo["pho_id"];

    //Ermittlung der Original Bildgroessee
    $bildgroesse = getimagesize("$ordner/$bild.jpg");

    // Erzeugung neues Bild
    $neubild = imagecreatetruecolor($bildgroesse[1], $bildgroesse[0]);

    //Aufrufen des Originalbildes
    $bilddaten = imagecreatefromjpeg("$ordner/$bild.jpg");

    //kopieren der Daten in neues Bild
    for($y=0; $y<$bildgroesse[1]; $y++)
    {
        for($x=0; $x<$bildgroesse[0]; $x++)
        {
            imagecopy($neubild, $bilddaten, $y, $bildgroesse[0]-$x-1, $x, $y, 1,1 );
        }
   }

    //ursprungsdatei loeschen
    if(file_exists("$ordner/$bild.jpg"))
    {
        chmod("$ordner/$bild.jpg", 0777);
        unlink("$ordner/$bild.jpg");
    }

    //speichern
    imagejpeg($neubild, "$ordner/$bild.jpg", 90);
    chmod("$ordner/$bild.jpg",0777);

    //Loeschen des Bildes aus Arbeitsspeicher
    imagedestroy($neubild);
    imagedestroy($bilddaten);
};

//Loeschen eines Bildes
function delete ($pho_id, $bild)
{
    global $g_current_user;
    global $g_adm_con;
    global $g_organization;
    global $act_datetime;

    //erfassen der Veranstaltung
    $sql = "    SELECT *
                FROM ". TBL_PHOTOS. "
                WHERE (pho_id ='$pho_id')";
    $result = mysql_query($sql, $g_adm_con);
    db_error($result,__FILE__,__LINE__);
    $adm_photo = mysql_fetch_array($result);

    //Speicherort
    $ordner = "../../../adm_my_files/photos/".$adm_photo["pho_begin"]."_".$adm_photo["pho_id"];

    //Bericht mit loeschen
    $neuebilderzahl = $adm_photo["pho_quantity"]-1;

    //Bilder loeschen
    if(file_exists("$ordner/$bild.jpg"))
    {
        chmod("$ordner/$bild.jpg", 0777);
        unlink("$ordner/$bild.jpg");
    }

    //Umbennenen der Restbilder
    $neuenr=1;
    for($x=1; $x<=$adm_photo["pho_quantity"]; $x++)
    {
        if(file_exists("$ordner/$x.jpg"))
        {
            if($x>$neuenr){
                chmod("$ordner/$x.jpg", 0777);
                rename("$ordner/$x.jpg", "$ordner/$neuenr.jpg");
            }//if
            $neuenr++;
        }//if
   }//for

   //&Auml;ndern der Datenbankeintaege
    $sql="  UPDATE ". TBL_PHOTOS. "
            SET pho_quantity = '$neuebilderzahl',
                pho_last_change ='$act_datetime',
                pho_usr_id_change = $g_current_user->id
            WHERE pho_id = '$pho_id'";
    $result = mysql_query($sql, $g_adm_con);
    db_error($result,__FILE__,__LINE__);
};

//Nutzung der rotatefunktion
if(isset($_GET["job"]) && $_GET["job"]=="rotate")
{
    //Aufruf der entsprechenden Funktion
    if($_GET["direction"]=="right"){
        right_rotate($pho_id, $_GET["bild"]);
    }
    if($_GET["direction"]=="left"){
        left_rotate($pho_id, $_GET["bild"]);
    }
    // zur Ausgangsseite zurueck
    $location = "Location: $g_root_path/adm_program/system/back.php";
    header($location);
    exit();
}

//Nachfrage ob geloescht werden soll
if(isset($_GET["job"]) && $_GET["job"]=="delete_request")
{
   $g_message->setForwardYesNo("$g_root_path/adm_program/modules/photos/photo_function.php?pho_id=$pho_id&bild=". $_GET["bild"]."&job=do_delete");
   $g_message->show("delete_photo");
}

//Nutzung der Loeschfunktion
if(isset($_GET["job"]) && $_GET["job"]=="do_delete")
{
    //erfassen der Veranstaltung
    $sql = "    SELECT *
                FROM ". TBL_PHOTOS. "
                WHERE (pho_id ='$pho_id')";
    $result = mysql_query($sql, $g_adm_con);
    db_error($result,__FILE__,__LINE__);
    $adm_photo = mysql_fetch_array($result);

    //Aufruf der entsprechenden Funktion
    delete($pho_id, $_GET["bild"]);

    $_SESSION['navigation']->deleteLastUrl();
    $g_message->setForwardUrl("$g_root_path/adm_program/system/back.php", 2000);
    $g_message->show("photo_deleted");
}
?>