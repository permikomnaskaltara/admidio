<?php
/******************************************************************************
 * Mitglieder einer Rolle zuordnen
 *
 * Copyright    : (c) 2004 - 2011 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * rol_id       : Rolle der Mitglieder hinzugefuegt oder entfernt werden sollen
 * mem_show_all : Begrenzte Userzahl:
 *            m - (Default) nur Mitglieder
 *            u - alle in der Datenbank gespeicherten user
 * mem_search   : Suchstring nach dem Mitglieder angezeigt werden sollen
 *
 *****************************************************************************/
require_once('../../system/common.php');
require_once('../../system/login_valid.php');
require_once('../../system/classes/table_roles.php');

//Uebergabevariablen pruefen
//Role ID
if(isset($_GET['rol_id']) && is_numeric($_GET['rol_id']) == false)
{
    $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
}
else
{
    $role_id = $_GET['rol_id'];
}

//Einschränkung nur Member oder alle User
$restrict = 'm';
if(isset($_POST['mem_show_all']) && $_POST['mem_show_all'] == 'on')
{
    $restrict = 'u';
}

//Suche
$search = '';
if(isset($_POST['mem_search']) && $_POST['mem_search']!='')
{
    $search = strStripTags($_POST['mem_search']);
}

// Objekt der uebergeben Rollen-ID erstellen
$role = new TableRoles($g_db, $role_id);

// nur Moderatoren duerfen Rollen zuweisen
// nur Webmaster duerfen die Rolle Webmaster zuweisen
// beide muessen Mitglied der richtigen Gliedgemeinschaft sein
if(  (!$g_current_user->assignRoles()
   && !isGroupLeader($g_current_user->getValue('usr_id'), $role_id))
|| (  !$g_current_user->isWebmaster()
   && $role->getValue('rol_name') == $g_l10n->get('SYS_WEBMASTER'))
|| ($role->getValue('cat_org_id') != $g_current_organization->getValue('org_id') && $role->getValue('cat_org_id') > 0 ))
{
    $g_message->show($g_l10n->get('SYS_NO_RIGHTS'));
}

$condition = '';
$limit = '';
if($restrict == 'm')
{
    //Falls gefordert, nur Aufruf von Inhabern der Rolle Mitglied
    $member_condition = ' EXISTS 
        (SELECT 1
           FROM '. TBL_MEMBERS. ', '. TBL_ROLES. ', '. TBL_CATEGORIES. '
          WHERE mem_usr_id = usr_id
            AND mem_rol_id = rol_id
            AND mem_begin <= \''.DATE_NOW.'\'
            AND mem_end    > \''.DATE_NOW.'\'
            AND rol_valid  = 1
            AND rol_cat_id = cat_id
            AND (  cat_org_id = '. $g_current_organization->getValue('org_id'). '
                OR cat_org_id IS NULL )) ';
}
elseif($restrict == 'u')
{
    //Falls gefordert, aufrufen alle Leute aus der Datenbank
    $member_condition = ' usr_valid = 1 ';
}

//Suchstring zerlegen
if($search != '')
{
    $search = str_replace('%', ' ', $search);
    $search_therms = explode(' ', $search);
    
    if(count($search_therms)>0)
    {
    	//in Condition einbinden
	    foreach($search_therms as $search_therm)
	    {
	    	$member_condition .= ' AND (  (UPPER(last_name.usd_value)  LIKE UPPER(\''.$search_therm.'%\')) 
									   OR (UPPER(first_name.usd_value) LIKE UPPER(\''.$search_therm.'%\'))) ';
	    }
    }
    //Ergebnissmenge Limitieren
    $limit .= ' LIMIT 30 ';
}


 // SQL-Statement zusammensetzen
$sql = 'SELECT DISTINCT usr_id, last_name.usd_value as last_name, first_name.usd_value as first_name, birthday.usd_value as birthday,
               city.usd_value as city, address.usd_value as address, zip_code.usd_value as zip_code, country.usd_value as country,
               mem_usr_id as member_this_role, mem_leader as leader_this_role,
                  (SELECT count(*)
                     FROM '. TBL_ROLES. ' rol2, '. TBL_CATEGORIES. ' cat2, '. TBL_MEMBERS. ' mem2
                    WHERE rol2.rol_valid   = 1
                      AND rol2.rol_cat_id  = cat2.cat_id
                      AND (  cat2.cat_org_id = '. $g_current_organization->getValue('org_id'). '
                          OR cat2.cat_org_id IS NULL )
                      AND mem2.mem_rol_id  = rol2.rol_id
                      AND mem2.mem_begin  <= \''.DATE_NOW.'\'
                      AND mem2.mem_end     > \''.DATE_NOW.'\'
                      AND mem2.mem_usr_id  = usr_id) as member_this_orga
        FROM '. TBL_USERS. '
        LEFT JOIN '. TBL_USER_DATA. ' as last_name
          ON last_name.usd_usr_id = usr_id
         AND last_name.usd_usf_id = '. $g_current_user->getProperty('LAST_NAME', 'usf_id'). '
        LEFT JOIN '. TBL_USER_DATA. ' as first_name
          ON first_name.usd_usr_id = usr_id
         AND first_name.usd_usf_id = '. $g_current_user->getProperty('FIRST_NAME', 'usf_id'). '
        LEFT JOIN '. TBL_USER_DATA. ' as birthday
          ON birthday.usd_usr_id = usr_id
         AND birthday.usd_usf_id = '. $g_current_user->getProperty('BIRTHDAY', 'usf_id'). '
        LEFT JOIN '. TBL_USER_DATA. ' as city
          ON city.usd_usr_id = usr_id
         AND city.usd_usf_id = '. $g_current_user->getProperty('CITY', 'usf_id'). '
        LEFT JOIN '. TBL_USER_DATA. ' as address
          ON address.usd_usr_id = usr_id
         AND address.usd_usf_id = '. $g_current_user->getProperty('ADDRESS', 'usf_id'). '
        LEFT JOIN '. TBL_USER_DATA. ' as zip_code
          ON zip_code.usd_usr_id = usr_id
         AND zip_code.usd_usf_id = '. $g_current_user->getProperty('POSTCODE', 'usf_id'). '
        LEFT JOIN '. TBL_USER_DATA. ' as country
          ON country.usd_usr_id = usr_id
         AND country.usd_usf_id = '. $g_current_user->getProperty('COUNTRY', 'usf_id'). '
        LEFT JOIN '. TBL_ROLES. ' rol
          ON rol.rol_valid   = 1
         AND rol.rol_id      = '.$role_id.'
        LEFT JOIN '. TBL_MEMBERS. ' mem
          ON mem.mem_rol_id  = rol.rol_id
         AND mem.mem_begin  <= \''.DATE_NOW.'\'
         AND mem.mem_end     > \''.DATE_NOW.'\'
         AND mem.mem_usr_id  = usr_id
        WHERE '. $member_condition. '
        ORDER BY last_name, first_name '.$limit;
$result_user = $g_db->query($sql);

if($g_db->num_rows($result_user)>0)
{
    //Buchstaben Navigation bei mehr als 50 personen
    if($g_db->num_rows($result_user) >= 50)
    {
        echo '<div class="pageNavigation">
            <a href="#" letter="all" class="pageNavigationLink">'.$g_l10n->get('SYS_ALL').'</a>&nbsp;&nbsp;';
        
            // Nun alle Buchstaben mit evtl. vorhandenen Links im Buchstabenmenue anzeigen
            $letter_menu = 'A';
            
            for($i = 0; $i < 26;$i++)
            {
                // pruefen, ob es Mitglieder zum Buchstaben gibt
                // dieses SQL muss fuer jeden Buchstaben ausgefuehrt werden, ansonsten werden Sonderzeichen nicht immer richtig eingeordnet
                $sql = 'SELECT COUNT(1) as count
                          FROM '. TBL_USERS. ', '. TBL_USER_FIELDS. ', '. TBL_USER_DATA. '
                         WHERE usr_valid  = 1
                           AND usf_name_intern = \'LAST_NAME\'
                           AND usd_usf_id = usf_id
                           AND usd_usr_id = usr_id
                           AND usd_value LIKE \''.$letter_menu.'%\'
                           AND '.$member_condition.'
                         GROUP BY UPPER(SUBSTRING(usd_value, 1, 1))
                         ORDER BY usd_value ';
                $result      = $g_db->query($sql);
                $letter_row  = $g_db->fetch_array($result);

                if($letter_row['count'] > 0)
                {
                    echo '<a href="#" letter="'.$letter_menu.'" class="pageNavigationLink">'.$letter_menu.'</a>';
                }
                else
                {
                    echo $letter_menu;
                }
        
                echo '&nbsp;&nbsp;';
        
                $letter_menu = strNextLetter($letter_menu);
            }
        echo '</div>';    
    }
    
    //Tabelle anlegen
    echo '
    <table class="tableList" cellspacing="0">
        <thead>
            <tr>
                <th><img class="iconInformation"
                    src="'. THEME_PATH. '/icons/profile.png" alt="'.$g_l10n->get('SYS_MEMBER_OF_ORGANIZATION', $g_current_organization->getValue('org_longname')).'"
                    title="'.$g_l10n->get('SYS_MEMBER_OF_ORGANIZATION', $g_current_organization->getValue('org_longname')).'" /></th>
                <th style="text-align: center;">'.$g_l10n->get('SYS_MEMBER').'</th>
                <th>'.$g_l10n->get('SYS_LASTNAME').'</th>
                <th>'.$g_l10n->get('SYS_FIRSTNAME').'</th>
                <th><img class="iconInformation" src="'. THEME_PATH. '/icons/map.png" 
                    alt="'.$g_l10n->get('SYS_ADDRESS').'" title="'.$g_l10n->get('SYS_ADDRESS').'" /></th>
                <th>'.$g_l10n->get('SYS_BIRTHDAY').'</th>
                <th style="text-align: center;">'.$g_l10n->get('SYS_LEADER').'<a rel="colorboxHelp" href="'. $g_root_path. '/adm_program/system/msg_window.php?message_id=SYS_LEADER_DESCRIPTION&amp;inline=true"><img 
	                onmouseover="ajax_showTooltip(event,\''.$g_root_path.'/adm_program/system/msg_window.php?message_id=SYS_LEADER_DESCRIPTION\',this)" onmouseout="ajax_hideTooltip()"
	                class="iconHelpLink" src="'. THEME_PATH. '/icons/help.png" alt="Help" title="" /></a></th>
            </tr>
        </thead>';
        
    $letter_merker = '';
    $this_letter   = '';
    
    function convSpecialChar($specialChar)
    {
        $convTable = array('Ä' => 'A', 'É' => 'E', 'È' => 'E', 'Ö' => 'O', 'Ü' => 'U');
        
        if(array_key_exists($specialChar, $convTable))
        {
            return admstrtoupper($convTable[$specialChar]);
        }
        return $specialChar;
    }

    //Zeilen ausgeben
    while($user = $g_db->fetch_array($result_user))
    {
    	if($g_db->num_rows($result_user) >= 50)
    	{
            // Buchstaben auslesen
            $this_letter = admstrtoupper(substr($user['last_name'], 0, 1));
            
            if(ord($this_letter) < 65 || ord($this_letter) > 90)
            {
                $this_letter = convSpecialChar(substr($user['last_name'], 0, 2));
            }
            
            if($this_letter != $letter_merker)
            {
                if(mb_strlen($letter_merker) > 0)
                {
                    echo '</tbody>';
                }

                // Ueberschrift fuer neuen Buchstaben
                echo '<tbody block_head_id="'.$this_letter.'" class="letterBlockHead">
                    <tr>
                        <td class="tableSubHeader" colspan="7">
                            '.$this_letter.'
                        </td>
                    </tr>
                </tbody>
                <tbody block_body_id="'.$this_letter.'" class="letterBlockBody">';

                // aktuellen Buchstaben merken
                $letter_merker = $this_letter;
            }
        }

        //Datensatz ausgeben
        $user_text = '';
        if(strlen($user['address']) > 0)
        {
            $user_text = $user['address'];
        }
        if(strlen($user['zip_code']) > 0 || strlen($user['city']) > 0)
        {
            $user_text = $user_text. ' - '. $user['zip_code']. ' '. $user['city'];
        }
        if(strlen($user['country']) > 0)
        {
            $user_text = $user_text. ' - '. $user['country'];
        }

        // Icon fuer Orgamitglied und Nichtmitglied auswaehlen
        if($user['member_this_orga'] > 0)
        {
            $icon = 'profile.png';
            $iconText = $g_l10n->get('SYS_MEMBER_OF_ORGANIZATION', $g_current_organization->getValue('org_longname'));
        }
        else
        {
            $icon = 'no_profile.png';
            $iconText = $g_l10n->get('SYS_NOT_MEMBER_OF_ORGANIZATION', $g_current_organization->getValue('org_longname'));
        }

        echo '
        <tr class="tableMouseOver" user_id="'.$user['usr_id'].'">
            <td><img class="iconInformation" src="'. THEME_PATH.'/icons/'.$icon.'" alt="'.$iconText.'" title="'.$iconText.'" /></td>

            <td style="text-align: center;">';
                //Haekchen setzen ob jemand Mitglied ist oder nicht
                if($user['member_this_role'])
                {
                    echo '<input type="checkbox" id="member_'.$user['usr_id'].'" name="member_'.$user['usr_id'].'" checked="checked" class="memlist_checkbox" checkboxtype="member" />';
                }
                else
                {
                    echo '<input type="checkbox" id="member_'.$user['usr_id'].'" name="member_'.$user['usr_id'].'" class="memlist_checkbox" checkboxtype="member"/>';
                }
            echo '<b id="loadindicator_member_'.$user['usr_id'].'"></b></td>
            <td>'.$user['last_name'].'</td>
            <td>'.$user['first_name'].'</td>
            <td>';
                if(strlen($user_text) > 0)
                {
                    echo '<img class="iconInformation" src="'. THEME_PATH.'/icons/map.png" alt="'.$user_text.'" title="'.$user_text.'" />';
                }
                else
                {
                    echo '&nbsp';
                }
            echo '</td>
            <td>';
                //Geburtstag nur ausgeben wenn bekannt
                if(strlen($user['birthday']) > 0)
                {
                    $birthdayDate = new DateTimeExtended($user['birthday'], 'Y-m-d', 'date');
                    echo $birthdayDate->format($g_preferences['system_date']);
                }
            echo '</td>

            <td style="text-align: center;">';
                //Haekchen setzen ob jemand Leiter ist oder nicht
                if($user['leader_this_role'])
                {
                    echo '<input type="checkbox" id="leader_'.$user['usr_id'].'" name="leader_'.$user['usr_id'].'" checked="checked" class="memlist_checkbox" checkboxtype="leader"/>';
                }
                else
                {
                    echo '<input type="checkbox" id="leader_'.$user['usr_id'].'" name="leader_'.$user['usr_id'].'" class="memlist_checkbox" checkboxtype="leader" />';
                }
            echo '<b id="loadindicator_leader_'.$user['usr_id'].'"></b>
        </tr>';
    }//End While

    echo '</table>
    <p>'.$g_l10n->get('SYS_CHECKBOX_AUTOSAVE').'</p>';
    
    //Hilfe nachladen
    echo '<script type="text/javascript">$("a[rel=\'colorboxHelp\']").colorbox({preloading:true,photo:false,speed:300,rel:\'nofollow\'})</script>';
}
else
{
	echo '<p>'.$g_l10n->get('SYS_NO_ENTRIES_FOUND').'</p>';
}
?>