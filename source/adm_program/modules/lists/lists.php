<?php
/******************************************************************************
 * Anzeigen von Listen
 *
 * Copyright    : (c) 2004 - 2008 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * start    - Angabe, ab welchem Datensatz Links angezeigt werden sollen
 * category - Kategorie der Rollen, die angezeigt werden sollen
 *            Wird keine Kategorie uebergeben, werden alle Rollen angezeigt
 * category-selection: yes - (Default) Anzeige der Combobox mit den verschiedenen
 *                           Rollen-Kategorien
 *                     no  - Combobox nicht anzeigen
 * active_role : 1 - (Default) aktive Rollen auflisten
 *               0 - inaktive Rollen auflisten
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/login_valid.php');
require_once('../../system/classes/table_roles.php');

unset($_SESSION['mylist_request']);

// Uebergabevariablen pruefen und ggf. vorbelegen

if(isset($_GET['start']) == false || is_numeric($_GET['start']) == false)
{
    $_GET['start'] = 0;
}

if(isset($_GET['category']) == false)
{
    $_GET['category'] = '';
}

if(isset($_GET['category-selection']) == false)
{
    $_GET['category-selection'] = 'yes';
    $show_ctg_sel = 1;
}
else
{
    if($_GET['category-selection'] == 'yes')
    {
        $show_ctg_sel = 1;
    }
    else
    {
        $show_ctg_sel = 0;
    }
}

if(isset($_GET['active_role']) == false)
{
    $active_role = 1;
}
else
{
    if($_GET['active_role'] != 0
    && $_GET['active_role'] != 1)
    {
        $active_role = 1;
    }
    else
    {
        $active_role = $_GET['active_role'];
    }
}

// Navigation faengt hier im Modul an
$_SESSION['navigation']->clear();
$_SESSION['navigation']->addUrl(CURRENT_URL);

// Alle Rollen-IDs ermitteln, die der User sehen darf
$rol_id_list = '';
if($active_role)
{
    foreach($g_current_user->getListViewRights() as $key => $value)
    {
        if($value == 1)
        {
            $rol_id_list = $rol_id_list. $key. ', ';
        }
    }
    if(strlen($rol_id_list) > 0)
    {
        $rol_id_list = ' AND rol_id IN ('. substr($rol_id_list, 0, strlen($rol_id_list)-2). ') ';
    }
    else
    {
        $rol_id_list = ' AND rol_id = 0 ';
    }
}

// SQL-Statement zusammensetzen
$sql = 'SELECT * FROM '. TBL_ROLES. ', '. TBL_CATEGORIES. '
         WHERE rol_valid  = '.$active_role.'
               '.$rol_id_list.'
           AND rol_cat_id = cat_id 
           AND cat_org_id = '. $g_current_organization->getValue('org_id');
if($g_valid_login == false)
{
    $sql .= ' AND cat_hidden = 0 ';
}
if(strlen($_GET['category']) > 0 && $_GET['category'] != 'Alle')
{
    // wenn eine Kategorie uebergeben wurde, dann nur Rollen dieser anzeigen
    $sql .= ' AND cat_type   = "ROL"
              AND cat_name   = "'. $_GET['category']. '" ';
}
$sql .= ' ORDER BY cat_sequence, rol_name ';

$result_lst = $g_db->query($sql);
$num_roles  = $g_db->num_rows();

if($num_roles == 0)
{
    if($g_valid_login == true)
    {
        // wenn User eingeloggt, dann Meldung, falls doch keine Rollen zur Verfuegung stehen
        if($active_role == 0)
        {
            $g_message->show('no_old_roles', '', 'Hinweis');
        }
        else
        {
            $g_message->show('no_enabled_lists', '', 'Hinweis');
        }
    }
    else
    {
        // wenn User ausgeloggt, dann Login-Bildschirm anzeigen
        require('../../system/login_valid.php');
    }
}

// Html-Kopf ausgeben
if($active_role)
{
    $g_layout['title']  = 'Übersicht der aktiven Rollen';
}
else
{
    $g_layout['title']  = 'Übersicht der inaktiven Rollen';
}
$g_layout['header'] = '
    <script type="text/javascript"><!--
        function showCategory()
        {
            var category = document.getElementById("category").value;
            self.location.href = "lists.php?category=" + category + "&category-selection='. $_GET['category-selection']. '&active_role='.$active_role.'";
        }

        function showList(element, rol_id)
        {
            var lst_id = element.value;

            if(lst_id == "mylist")
            {
                self.location.href = gRootPath + "/adm_program/modules/lists/mylist.php?rol_id=" + rol_id';
                if($active_role == false)
                {
                    $g_layout['header'] .= ' + "&show_members=1"';
                }
                $g_layout['header'] .= ';
            }
            else
            {
                self.location.href = gRootPath + "/adm_program/modules/lists/lists_show.php?mode=html&lst_id=" + lst_id + "&rol_id=" + rol_id;
            }
        }

	    function toggleDetails(role_details_ID, triangle_ID)
        {
            if($("#" + role_details_ID).css("display") == "none")
            {
                $("#" + role_details_ID).show("slow");
                $("#" + triangle_ID).attr("src",   gThemePath + "/icons/triangle_open.gif");
                $("#" + triangle_ID).attr("title", "Details ausblenden");
                $("#" + triangle_ID).attr("alt",   "Details ausblenden");
            }
            else
            {
                $("#" + role_details_ID).hide("slow");
                $("#" + triangle_ID).attr("src",   gThemePath + "/icons/triangle_close.gif");
                $("#" + triangle_ID).attr("title", "Details einblenden");
                $("#" + triangle_ID).attr("alt",   "Details einblenden");
            }
        }
    //--></script>';

require(THEME_SERVER_PATH. '/overall_header.php');

// Html des Modules ausgeben
echo '<div id="lists_overview">
<h1 class="moduleHeadline">'. $g_layout['title']. '</h1>';

// Kategorienauswahlbox soll angezeigt werden oder der User darf neue Rollen anlegen
if($show_ctg_sel == 1 || $g_current_user->assignRoles())
{
    echo '<ul class="iconTextLinkList">';
    //Neue Termine anlegen
    if($g_current_user->editDates())
    {
        echo '
        <li>
            <span class="iconTextLink">
                <a href="'.$g_root_path.'/adm_program/administration/roles/roles_new.php"><img
                    src="'.THEME_PATH.'/icons/add.png" alt="Rolle anlegen" /></a>
                <a href="'.$g_root_path.'/adm_program/administration/roles/roles_new.php">Rolle anlegen</a>
            </span>
        </li>';
    }

    if($show_ctg_sel == 1)
    {
        // Combobox mit allen Kategorien anzeigen, denen auch Rollen zugeordnet sind
        $sql = 'SELECT DISTINCT cat_name 
                  FROM '. TBL_CATEGORIES. ', '. TBL_ROLES. '
                 WHERE cat_org_id = '. $g_current_organization->getValue('org_id'). '
                   AND cat_type   = "ROL" 
                   AND rol_cat_id = cat_id 
                       '.$rol_id_list;
        if($g_valid_login == false)
        {
            $sql .= ' AND cat_hidden = 0 ';
        }
        $sql .= ' ORDER BY cat_sequence ASC ';
        $result = $g_db->query($sql);

        if($g_db->num_rows($result) > 1)
        {
            echo '<li>Kategorie:&nbsp;&nbsp;
            <select size="1" id="category" onchange="showCategory()">
                <option value="Alle" ';
                if(strlen($_GET['category']) == 0)
                {
                    echo ' selected="selected" ';
                }
                echo '>Alle</option>';

                while($row = $g_db->fetch_object($result))
                {
                    echo '<option value="'. urlencode($row->cat_name). '"';
                    if($_GET['category'] == $row->cat_name)
                    {
                        echo ' selected="selected" ';
                    }
                    echo '>'.$row->cat_name.'</option>';
                }
            echo '</select>';
            if($g_current_user->assignRoles())
            {
                echo '<a  class="iconLink" href="'.$g_root_path.'/adm_program/administration/roles/categories.php?type=ROL">
                    <img src="'. THEME_PATH. '/icons/options.png" alt="Kategorien pflegen" title="Kategorien pflegen" />
                </a>';
            }
            echo '</li>';
        }
        elseif($g_current_user->assignRoles())
        {
            echo '
            <li><span class="iconTextLink">
                <a href="'.$g_root_path.'/adm_program/administration/roles/categories.php?type=ROL"><img
                    src="'. THEME_PATH. '/icons/application_double.png" alt="Kategorien pflegen" title="Kategorien pflegen"/></a>
                <a href="'.$g_root_path.'/adm_program/administration/roles/categories.php?type=ROL">Kategorien pflegen</a>
            </span></li>';
        }    
    }
    echo '</ul>';
}

$previous_cat_id   = 0;
$count_cat_entries = 0;
// jetzt erst einmal zu dem ersten relevanten Datensatz springen
if(!$g_db->data_seek($result_lst, $_GET['start']))
{
    $g_message->show('invalid');
}

// SQL-Statement fuer alle Listenkonfigurationen vorbereiten, die angezeigt werdne sollen
$sql = 'SELECT lst_id, lst_name, lst_global FROM '. TBL_LISTS. '
     WHERE lst_org_id = '. $g_current_organization->getValue('org_id'). '
       AND (  lst_usr_id = '. $g_current_user->getValue('usr_id'). '
           OR lst_global = 1)
       AND lst_name IS NOT NULL
     ORDER BY lst_global ASC, lst_name ASC';
$result_config = $g_db->query($sql);

// Anzahl Rollen pro Seite
if($g_preferences['lists_roles_per_page'] > 0)
{
    $roles_per_page = $g_preferences['lists_roles_per_page'];
}
else
{
    $roles_per_page = $num_roles;
}

// Rollenobjekt anlegen
$role = new TableRoles($g_db);

for($i = 0; $i < $roles_per_page && $i + $_GET['start'] < $num_roles; $i++)
{
    if($row_lst = $g_db->fetch_array($result_lst))
    {
    	// Rollenobjekt mit Daten fuellen
    	$role->setArray($row_lst);

        if($active_role == 1)
        {
            $sql_member_status = ' AND mem_begin <= "'.DATE_NOW.'"
                                   AND mem_end    > "'.DATE_NOW.'" ';
        }
        else
        {
            $sql_member_status = ' AND mem_end < "'.DATE_NOW.'" ';
        }
        // Anzahl Mitglieder ermitteln die keine Leiter sind
        $sql = 'SELECT COUNT(*) as count
                  FROM '. TBL_MEMBERS. '
                 WHERE mem_rol_id = '. $role->getValue('rol_id'). '
                       '.$sql_member_status.'
                   AND mem_leader = 0';
        $result = $g_db->query($sql);
        $row    = $g_db->fetch_array($result);
        $num_member = $row['count'];

         // Anzahl Mitglieder ermitteln die Leiter sind
        $sql = 'SELECT COUNT(*) as count
                  FROM '. TBL_MEMBERS. '
                 WHERE mem_rol_id = '. $role->getValue('rol_id'). '
                       '.$sql_member_status.'
                   AND mem_leader = 1';
        $result = $g_db->query($sql);
        $row    = $g_db->fetch_array($result);
        $num_leader = $row['count'];

        if($active_role)
        {
            // Anzahl ehemaliger Mitglieder ermitteln
            $sql = 'SELECT COUNT(*) as count
                      FROM '. TBL_MEMBERS. '
                     WHERE mem_rol_id = '. $role->getValue('rol_id'). '
                       AND mem_end   <= "'. DATE_NOW.'"';
            $result = $g_db->query($sql);
            $row    = $g_db->fetch_array($result);
            $num_former = $row['count'];
        }

        if($previous_cat_id != $role->getValue('cat_id'))
        {
            
            if($i > 0)
            {
                if($count_cat_entries == 0)
                {
                    echo 'Diese Kategorie enthält keine zur Ansicht freigegebenen Listen.';
                }
                echo '</div></div><br />';
            }
            echo '<div class="formLayout">
                <div class="formHead">'. $role->getValue('cat_name'). '</div>
                <div class="formBody">';
            $previous_cat_id = $role->getValue('cat_id');
            $count_cat_entries = 0;
        }

        
        //Nur anzeigen, wenn User auch die Liste einsehen darf
        if($g_current_user->viewRole($role->getValue('rol_id')))
        {
            if($count_cat_entries > 0)
            {
                echo '<hr />';
            }
            echo '
            <div>
                <div style="float: left;">';
                    //Dreieck zum ein und ausblenden der Details
			        if($g_preferences['lists_hide_overview_details']==1)
	                {
	                    echo '<a class="iconLink" href="javascript:toggleDetails(\'role_details_'.$role->getValue('rol_id').'\', \'triangle_'.$role->getValue('rol_id').'\')">
							<img id="triangle_'.$role->getValue('rol_id').'"  src="'. THEME_PATH. '/icons/triangle_close.gif" alt="Details einblende" title="Details einblende" /></a>'; 
	                }
                    else
                    {
                        echo '<a class="iconLink" href="javascript:toggleDetails(\'role_details_'.$role->getValue('rol_id').'\', \'triangle_'.$role->getValue('rol_id').'\')">
							<img id="triangle_'.$role->getValue('rol_id').'"  src="'. THEME_PATH. '/icons/triangle_open.gif" alt="Details ausblenden" title="Details ausblenden" /></a>';
                    }

                    // Link nur anzeigen, wenn Rolle auch Mitglieder hat
                    if($num_member > 0 || $num_leader > 0)
                    {
                        echo '<a href="'.$g_root_path.'/adm_program/modules/lists/lists_show.php?mode=html&amp;rol_id='. $role->getValue('rol_id'). '">'. $role->getValue('rol_name'). '</a>';
                    }
                    else
                    {
                        echo '<strong>'. $role->getValue('rol_name'). '</strong>';
                    }
    
		        	//Mail an Rolle schicken
                    if($g_current_user->mailRole($role->getValue('rol_id')) && $g_preferences['enable_mail_module'] == 1)
		            {
		                echo '
						<a class="iconLink" href="'.$g_root_path.'/adm_program/modules/mail/mail.php?rol_id='.$role->getValue('rol_id').'"><img
		                	src="'. THEME_PATH. '/icons/email.png"  alt="E-Mail an Mitglieder" title="E-Mail an Mitglieder" /></a>';
		            }
                    
		            if($g_current_user->assignRoles() 
                    || isGroupLeader($g_current_user->getValue('usr_id'), $role->getValue('rol_id')) 
                    || $g_current_user->editUsers())
                    {
                        if($role->getValue('rol_name') != 'Webmaster'
                        || ($role->getValue('rol_name') == 'Webmaster' && $g_current_user->isWebmaster()))
                        {
                            if($g_current_user->assignRoles())
                            {
                                // nur Moderatoren duerfen Rollen editieren
                                echo '
                                <a class="iconLink" href="'.$g_root_path.'/adm_program/administration/roles/roles_new.php?rol_id='.$role->getValue('rol_id').'"><img
                                    src="'.THEME_PATH.'/icons/edit.png" alt="Einstellungen" title="Einstellungen" /></a>';
                            }
    
                            // Gruppenleiter und Moderatoren duerfen Mitglieder zuordnen oder entfernen (nicht bei Ehemaligen Rollen)
                            if($role->getValue("rol_valid") == 1)
                            {
                                echo '
                                <a class="iconLink" href="'.$g_root_path.'/adm_program/modules/lists/members.php?rol_id='.$role->getValue('rol_id').'"><img 
                                    src="'.THEME_PATH.'/icons/add.png" alt="Mitglieder zuordnen" title="Mitglieder zuordnen" /></a>';
                            }
                        }
                    }
                echo '</div>
                <div style="text-align: right;">';
                    // Kombobox mit Listen nur anzeigen, wenn die Rolle Mitglieder hat
                    if($num_member > 0 || $num_leader > 0)
                    {
                        echo '
                        <select size="1" name="list'.$i.'" onchange="showList(this, '. $role->getValue('rol_id'). ')">
                            <option value="" selected="selected">Liste anzeigen ...</option>';
                            
                            // alle globalen Listenkonfigurationen auflisten
                            $g_db->data_seek($result_config, 0);
                            $list_global_flag = '';
                            
                            while($row = $g_db->fetch_array($result_config))
                            {
                                if($list_global_flag != $row['lst_global'])
                                {
                                    if($row['lst_global'] == 0)
                                    {
                                        echo '<optgroup label="Gespeicherte Listen">';
                                    }
                                    else
                                    {
                                        if($list_global_flag > 0)
                                        {
                                            echo '</optgroup>';
                                        }
                                        echo '<optgroup label="Allgemeine Listen">';
                                    }
                                    $list_global_flag = $row['lst_global'];
                                }
                                echo '<option value="'.$row['lst_id'].'">'.$row['lst_name'].'</option>';
                            }
                            
                            // Link zu den eigenen Listen setzen
                            echo '</optgroup>
                            <optgroup label="Konfiguration">
                                <option value="mylist">Eigene Liste ...</option>
                            </optgroup>
                        </select>';
                    }
                    else
                    {
                        echo '&nbsp;';
                    }
                echo '</div>
            </div>
            
            <ul id="role_details_'.$role->getValue('rol_id').'" ';
                if($g_preferences['lists_hide_overview_details']==1)
                {
                    echo ' style="display: none;" '; 
                }
                echo ' class="formFieldList">';
                if(strlen($role->getValue('rol_description')) > 0)
                {
                    echo '
                    <li>
                        <dl>
                            <dt>Beschreibung:</dt>
                            <dd>'.$role->getValue('rol_description').'</dd>
                        </dl>
                    </li>';
                }
    
                if(strlen($role->getValue('rol_start_date')) > 0)
                {
                    echo '
                    <li>
                        <dl>
                            <dt>Zeitraum:</dt>
                            <dd>'. mysqldate('d.m.y', $role->getValue('rol_start_date')). ' bis '. mysqldate('d.m.y', $role->getValue('rol_end_date')). '</dd>
                        </dl>
                    </li>';
                }
                if($role->getValue('rol_weekday') > 0
                || strlen($role->getValue('rol_start_time')) > 0 )
                {
                    echo '
                    <li>
                        <dl>
                            <dt>Gruppenstunde:</dt>
                            <dd>'; 
                                if($role->getValue('rol_weekday') > 0)
                                {
                                    echo $arrDay[$role->getValue('rol_weekday')-1];
                                }
                                if(strlen($role->getValue('rol_start_time')) > 0)
                                {
                                    echo ' von '. mysqltime('h:i', $role->getValue('rol_start_time')). ' bis '. mysqltime('h:i', $role->getValue('rol_end_time'));
                                }
                            echo '</dd>
                        </dl>
                    </li>';
                }
                //Treffpunkt
                if(strlen($role->getValue('rol_location')) > 0)
                {
                    echo '
                    <li>
                        <dl>
                            <dt>Treffpunkt:</dt>
                            <dd>'.$role->getValue('rol_location').'</dd>
                        </dl>
                    </li>';
                }
                //Teinehmer
                echo '
                <li>
                    <dl>
                        <dt>Teilnehmer:</dt>
                        <dd>'.$num_member;
                            if($role->getValue('rol_max_members') > 0)
                            {
                                echo ' von max. '. $role->getValue('rol_max_members');
                            }
                            if($active_role && $num_former > 0)
                            {
                                // Anzahl Ehemaliger anzeigen
                                if($num_former == 1)
                                {
                                    $text_former = 'Ehemaliger';
                                }
                                else
                                {
                                    $text_former = 'Ehemalige';
                                }
                                echo '&nbsp;&nbsp;(<a href="'.$g_root_path.'/adm_program/modules/lists/lists_show.php?mode=html&amp;rol_id='. $role->getValue('rol_id'). '&amp;show_members=1">'.$num_former.' '.$text_former.'</a>) ';
                            }
                        echo '</dd>
                    </dl>
                </li>';
    
                //Leiter
                if($num_leader>0)
                {
                    echo '
                    <li>
                        <dl>
                            <dt>Leiter:</dt>
                            <dd>'.$num_leader.'</dd>
                        </dl>
                    </li>';
                }
    
                //Beitrag
                if(strlen($role->getValue('rol_cost')) > 0)
                {
                    echo '
                    <li>
                        <dl>
                            <dt>Beitrag:</dt>
                            <dd>'.$role->getValue('rol_cost').' &euro;</dd>
                        </dl>
                    </li>';
                }
				
				//Beitragszeitraum
                if(strlen($role->getValue('rol_cost_period')) > 0 && $role->getValue('rol_cost_period') != 0)
                {
                    echo'<li>
                        <dl>
                            <dt>Beitragszeitraum:</dt>
                            <dd>'.TableRoles::getRolCostPeriodDesc($role->getValue('rol_cost_period')).'</dd>
                        </dl>
                    </li>';
                }
				
            echo '</ul>';
            $count_cat_entries++;
        }
        else
        {
            $num_roles--;
        }
    }
}

if($count_cat_entries == 0)
{
    echo 'Diese Kategorie enthält keine zur Ansicht freigegebenen Listen.';
}
echo '</div></div>';

// Navigation mit Vor- und Zurueck-Buttons
$base_url = $g_root_path.'/adm_program/modules/lists/lists.php?category='. $_GET['category']. '&category-selection='. $_GET['category-selection']. '&active_role='.$active_role;
echo generatePagination($base_url, $num_roles, $roles_per_page, $_GET['start'], TRUE);

echo '</div>';

require(THEME_SERVER_PATH. '/overall_footer.php');

?>