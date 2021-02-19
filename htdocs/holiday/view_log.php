<?php
/* Copyright (C) 2007-2016 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2011      Dimitri Mouillard    <dmouillard@teclib.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *  Displays the log of actions performed in the module.
 *
 *  \file       htdocs/holiday/view_log.php
 *  \ingroup    holiday
 */

require '../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
require_once DOL_DOCUMENT_ROOT.'/holiday/common.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';

$action     = GETPOST('action', 'aZ09') ?GETPOST('action', 'aZ09') : 'view'; // The action 'add', 'create', 'edit', 'update', 'view', ...
$massaction = GETPOST('massaction', 'alpha'); // The bulk action (combo box choice into lists)
$show_files = GETPOST('show_files', 'int'); // Show files area generated by bulk actions ?
$confirm    = GETPOST('confirm', 'alpha'); // Result of a confirmation
$cancel     = GETPOST('cancel', 'alpha'); // We click on a Cancel button
$toselect   = GETPOST('toselect', 'array'); // Array of ids of elements selected into a list
$contextpage = GETPOST('contextpage', 'aZ') ?GETPOST('contextpage', 'aZ') : 'myobjectlist'; // To manage different context of search
$backtopage = GETPOST('backtopage', 'alpha'); // Go back to a dedicated page
$optioncss  = GETPOST('optioncss', 'aZ'); // Option for the css output (always '' except when 'print')

$search_id  = GETPOST('search_id', 'alpha');
$year = GETPOST('year');
if (empty($year))
{
    $tmpdate = dol_getdate(dol_now());
    $year = $tmpdate['year'];
}

// Load variable for pagination
$limit = GETPOST('limit', 'int') ?GETPOST('limit', 'int') : $conf->liste_limit;
$sortfield = GETPOST('sortfield', 'alpha');
$sortorder = GETPOST('sortorder', 'alpha');
$page = GETPOSTISSET('pageplusone') ? (GETPOST('pageplusone') - 1) : GETPOST("page", 'int');
if (empty($page) || $page == -1 || GETPOST('button_search', 'alpha') || GETPOST('button_removefilter', 'alpha') || (empty($toselect) && $massaction === '0')) { $page = 0; }     // If $page is not defined, or '' or -1 or if we click on clear filters or if we select empty mass action
$offset = $limit * $page;
$pageprev = $page - 1;
$pagenext = $page + 1;
//if (! $sortfield) $sortfield="p.date_fin";
//if (! $sortorder) $sortorder="DESC";


// Protection if external user
if ($user->socid > 0) accessforbidden();

// Si l'utilisateur n'a pas le droit de lire cette page
if (!$user->rights->holiday->read_all) accessforbidden();

// Load translation files required by the page
$langs->load('users');

// Initialize technical objects
$object = new Holiday($db);
$extrafields = new ExtraFields($db);
//$diroutputmassaction = $conf->mymodule->dir_output . '/temp/massgeneration/'.$user->id;
$hookmanager->initHooks(array('leavemovementlist')); // Note that conf->hooks_modules contains array

$arrayfields = array();
$arrayofmassactions = array();


/*
 * Actions
 */

if (GETPOST('cancel', 'alpha')) { $action = 'list'; $massaction = ''; }
if (!GETPOST('confirmmassaction', 'alpha') && $massaction != 'presend' && $massaction != 'confirm_presend') { $massaction = ''; }

$parameters = array();
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action); // Note that $action and $object may have been modified by some hooks
if ($reshook < 0) setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');

if (empty($reshook))
{
    // Selection of new fields
    include DOL_DOCUMENT_ROOT.'/core/actions_changeselectedfields.inc.php';

    // Purge search criteria
    if (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')) // All tests are required to be compatible with all browsers
    {
        $search_id = '';
        $toselect = '';
        $search_array_options = array();
    }
    if (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')
        || GETPOST('button_search_x', 'alpha') || GETPOST('button_search.x', 'alpha') || GETPOST('button_search', 'alpha'))
    {
        $massaction = ''; // Protection to avoid mass action if we force a new search during a mass action confirmation
    }

    // Mass actions
    /*$objectclass='MyObject';
    $objectlabel='MyObject';
    $permissiontoread = $user->rights->mymodule->read;
    $permissiontodelete = $user->rights->mymodule->delete;
    $uploaddir = $conf->mymodule->dir_output;
    include DOL_DOCUMENT_ROOT.'/core/actions_massactions.inc.php';
    */
}



/*
 * View
 */

$form = new Form($db);

$alltypeleaves = $object->getTypes(1, -1); // To have labels

llxHeader('', $langs->trans('CPTitreMenu').' ('.$langs->trans("Year").' '.$year.')');



$sqlwhere = '';
$sqlwhere .= " AND date_action BETWEEN '".$db->idate(dol_get_first_day($year, 1, 1))."' AND '".$db->idate(dol_get_last_day($year, 12, 1))."'";
if ($search_id != '') $sqlwhere .= natural_search('rowid', $search_id, 1);

$sqlorder = 'ORDER BY cpl.rowid DESC';

// Recent changes are more important than old changes
$log_holiday = $object->fetchLog($sqlorder, $sqlwhere); // Load $object->logs

$param = '';
if (!empty($contextpage) && $contextpage != $_SERVER["PHP_SELF"]) $param .= '&contextpage='.urlencode($contextpage);
if ($limit > 0 && $limit != $conf->liste_limit) $param .= '&limit='.urlencode($limit);
if ($search_id) $param = '&search_id='.urlencode($search_id);

print '<form method="POST" id="searchFormList" action="'.$_SERVER["PHP_SELF"].'">';
if ($optioncss != '') print '<input type="hidden" name="optioncss" value="'.$optioncss.'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="formfilteraction" id="formfilteraction" value="list">';
print '<input type="hidden" name="action" value="list">';
print '<input type="hidden" name="sortfield" value="'.$sortfield.'">';
print '<input type="hidden" name="sortorder" value="'.$sortorder.'">';
print '<input type="hidden" name="page" value="'.$page.'">';
print '<input type="hidden" name="contextpage" value="'.$contextpage.'">';

$pagination = '<div class="pagination"><ul><li class="pagination"><a href="'.$_SERVER["PHP_SELF"].'?year='.($year - 1).$param.'"><i class="fa fa-chevron-left" title="Previous"></i></a><li class="pagination"><span class="active">'.$langs->trans("Year").' '.$year.'</span></li><li class="pagination"><a href="'.$_SERVER["PHP_SELF"].'?year='.($year + 1).$param.'"><i class="fa fa-chevron-right" title="Next"></i></a></li></lu></div>';
print load_fiche_titre($langs->trans('LogCP'), $pagination, 'title_hrm.png');

print '<div class="info">'.$langs->trans('LastUpdateCP').': '."\n";
$lastUpdate = $object->getConfCP('lastUpdate');
if ($lastUpdate)
{
    $monthLastUpdate = $lastUpdate[4].$lastUpdate[5];
    $yearLastUpdate = $lastUpdate[0].$lastUpdate[1].$lastUpdate[2].$lastUpdate[3];
    print '<strong>'.dol_print_date($db->jdate($object->getConfCP('lastUpdate')), 'dayhour', 'tzuser').'</strong>';
    print '<br>'.$langs->trans("MonthOfLastMonthlyUpdate").': <strong>'.$yearLastUpdate.'-'.$monthLastUpdate.'</strong>'."\n";
}
else print $langs->trans('None');
print "</div><br>\n";

$moreforfilter = '';

$varpage = empty($contextpage) ? $_SERVER["PHP_SELF"] : $contextpage;
//$selectedfields=$form->multiSelectArrayWithCheckbox('selectedfields', $arrayfields, $varpage);	// This also change content of $arrayfields
//$selectedfields.=(count($arrayofmassactions) ? $form->showCheckAddButtons('checkforselect', 1) : '');
$selectedfields = '';

print '<div class="div-table-responsive">';
print '<table class="tagtable liste'.($moreforfilter ? " listwithfilterbefore" : "").'" id="tablelines3">'."\n";

print '<tbody>';

print '<tr class="liste_titre">';
print '<td class="liste_titre"><input type="text" class="maxwidth50" name="search_id" value="'.$search_id.'"></td>';
print '<td class="liste_titre"></td>';
print '<td class="liste_titre"></td>';
print '<td class="liste_titre"></td>';
print '<td class="liste_titre"></td>';
print '<td class="liste_titre"></td>';
print '<td class="liste_titre"></td>';
print '<td class="liste_titre"></td>';
print '<td class="liste_titre"></td>';
// Action column
print '<td class="liste_titre maxwidthsearch">';
$searchpicto = $form->showFilterButtons();
print $searchpicto;
print '</td>';
print '</tr>';

print '<tr class="liste_titre">';
print_liste_field_titre('ID');
print_liste_field_titre('Date', $_SERVER["PHP_SELF"], '', '', '', '', '', '', 'center ');
print_liste_field_titre('ActionByCP');
print_liste_field_titre('UserUpdateCP');
print_liste_field_titre('Description');
print_liste_field_titre('Type');
print_liste_field_titre('PrevSoldeCP', $_SERVER["PHP_SELF"], '', '', '', '', '', '', 'right ');
print_liste_field_titre('Variation', $_SERVER["PHP_SELF"], '', '', '', '', '', '', 'right ');
print_liste_field_titre('NewSoldeCP', $_SERVER["PHP_SELF"], '', '', '', '', '', '', 'right ');
print getTitleFieldOfList($selectedfields, 0, $_SERVER["PHP_SELF"], '', '', '', '', $sortfield, $sortorder, 'center maxwidthsearch ')."\n";
print '</tr>';


foreach ($object->logs as $logs_CP)
{
   	$user_action = new User($db);
   	$user_action->fetch($logs_CP['fk_user_action']);

   	$user_update = new User($db);
   	$user_update->fetch($logs_CP['fk_user_update']);

   	$delta = price2num($logs_CP['new_solde'] - $logs_CP['prev_solde'], 5);
   	$detasign = ($delta > 0 ? '+' : '-');

   	print '<tr class="oddeven">';
   	print '<td>'.$logs_CP['rowid'].'</td>';
   	print '<td style="text-align: center;">'.$logs_CP['date_action'].'</td>';
   	print '<td>'.$user_action->getNomUrl(-1).'</td>';
   	print '<td>'.$user_update->getNomUrl(-1).'</td>';
   	print '<td>'.$logs_CP['type_action'].'</td>';
   	print '<td>';
   	$label = (($alltypeleaves[$logs_CP['fk_type']]['code'] && $langs->trans($alltypeleaves[$logs_CP['fk_type']]['code']) != $alltypeleaves[$logs_CP['fk_type']]['code']) ? $langs->trans($alltypeleaves[$logs_CP['fk_type']]['code']) : $alltypeleaves[$logs_CP['fk_type']]['label']);
	print $label ? $label : $logs_CP['fk_type'];
   	print '</td>';
   	print '<td style="text-align: right;">'.price2num($logs_CP['prev_solde'], 5).' '.$langs->trans('days').'</td>';
   	print '<td style="text-align: right;">'.$detasign.$delta.'</td>';
   	print '<td style="text-align: right;">'.price2num($logs_CP['new_solde'], 5).' '.$langs->trans('days').'</td>';
   	print '<td></td>';
   	print '</tr>'."\n";
}

if ($log_holiday == '2')
{
    print '<tr class="opacitymedium">';
    print '<td colspan="10" class="opacitymedium">'.$langs->trans('NoRecordFound').'</td>';
    print '</tr>';
}

print '</tbody>'."\n";
print '</table>'."\n";
print '</div>';

print '</form>';

// End of page
llxFooter();
$db->close();
