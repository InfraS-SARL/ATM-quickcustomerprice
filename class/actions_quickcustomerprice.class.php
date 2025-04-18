<?php
/* <one line to give the program's name and a brief idea of what it does.>
 * Copyright (C) 2015 ATM Consulting <support@atm-consulting.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * \file    class/actions_quickcustomerprice.class.php
 * \ingroup quickcustomerprice
 * \brief   This file is an example hook overload class file
 *          Put some comments here
 */

/**
 * Class Actionsquickcustomerprice
 */
require_once __DIR__ . '/../backport/v19/core/class/commonhookactions.class.php';

class Actionsquickcustomerprice extends quickcustomerprice\RetroCompatCommonHookActions
{
	/**
	 * @var array Hook results. Propagated to $hookmanager->resArray for later reuse
	 */
	public $results = array();
	/**
	 * @var string String displayed by executeHook() immediately after return
	 */
	public $resprints;
	/**
	 * @var array Errors
	 */
	public $errors = array();

	/**
	 * Constructor
	 */
	public function __construct() {
	}

	/**
	 * Overloading the doActions function : replacing the parent's function with the one below
	 *
	 * @param array()         $parameters     Hook metadatas (context, etc...)
	 * @param CommonObject    &$object The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param string          &$action Current action (if set). Generally create or edit or null
	 * @param HookManager $hookmanager Hook manager propagated to allow calling another hook
	 * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	 */
	function formObjectOptions($parameters, &$object, &$action, $hookmanager) {
		global $user, $conf;

		$error = 0;

		if (
			$parameters['currentcontext'] == 'propalcard'
			|| $parameters['currentcontext'] == 'ordercard'
			|| $parameters['currentcontext'] == 'invoicecard'
			|| (getDolGlobalString('QCP_ENABLE_SUPPLIER_PART') && (
					$parameters['currentcontext'] == 'ordersuppliercard'
					|| $parameters['currentcontext'] == 'invoicesuppliercard'
					|| $parameters['currentcontext'] == 'supplier_proposalcard'
				)
			)
		) {
			global $langs;

			dol_include_once('/compta/facture/class/facture.class.php');
			dol_include_once('/comm/propal/class/propal.class.php');
			dol_include_once('/commande/class/commande.class.php');

			if ($object->statut > 0 && ! getDolGlobalString('QCP_ALLOW_CHANGE_ON_VALIDATE')) return 0;

			$TIDLinesToChange = $this->_getTIDLinesToChange($object);

			// enable hooks to register callbacks into the priceCallbacks array (the function are called
			// at the end of the default callback when the Ajax call returns)
			?>
            <script type="text/javascript">priceCallbacks = [];</script><?php
			$reshook = $hookmanager->executeHooks('addJSCallbacks', $parameters, $object, $action);
			if ($reshook >= 0) {
				echo $hookmanager->resPrint;
			}
			?>
            <script type="text/javascript">
                $(document).ready(function () {

                    var TIDLinesToChange = <?php echo json_encode($TIDLinesToChange); ?>;
					<?php
					$strToFind = array();

					// Pour les facture de situations, on peut modifier le P.U. HT et les Qtes uniquement s'il n'y a qu'une situation dans le cycle
					if ($user->hasRight('quickcustomerprice', 'edit_unit_price') && $object->element != 'facture'
						|| $user->hasRight('quickcustomerprice', 'edit_unit_price') && $object->element == 'facture' && $object->type != Facture::TYPE_SITUATION
						|| $user->hasRight('quickcustomerprice', 'edit_unit_price') && $object->element == 'facture' && $object->type == Facture::TYPE_SITUATION && empty($object->tab_previous_situation_invoice) && empty($object->tab_next_situation_invoice)) {
						$strToFind[] = 'td.linecoluht';
					}

					// Pour les facture de situations, on peut modifier le P.U. HT et les Qtes uniquement s'il n'y a qu'une situation dans le cycle
					if ($user->hasRight('quickcustomerprice', 'edit_quantity') && $object->element != 'facture'
						|| $user->hasRight('quickcustomerprice', 'edit_quantity') && $object->element == 'facture' && $object->type != Facture::TYPE_SITUATION
						|| $user->hasRight('quickcustomerprice', 'edit_quantity') && $object->element == 'facture' && $object->type == Facture::TYPE_SITUATION && empty($object->tab_previous_situation_invoice) && empty($object->tab_next_situation_invoice)) {
						$strToFind[] = 'td.linecolqty';
					}
					if ($user->hasRight('quickcustomerprice', 'edit_discount')) $strToFind[] = 'td.linecoldiscount';
					if (isModEnabled('margin')) {
						$strToFind[] = 'td.linecolmargin1';
						if (floatval(DOL_VERSION) >= 21.0) {
							$strToFind[] = 'td.linecolmargin2';
							$strToFind[] = 'td.linecolmark1';
						}
					}
					if (isModEnabled('multicurrency')) $strToFind[] = 'td.linecoluht_currency';
					?>
                    $('table#tablelines tr[id]').find('<?php echo implode(',', $strToFind); ?>' + ',td.linecolcycleref').each(function (i, item) {
                        value = $(item).html();
                        if (value == '&nbsp;') value = '';

                        lineid = $(item).closest('tr').attr('id').substr(4);

                        if (TIDLinesToChange.indexOf(lineid) == -1) return;

                        if ($(item).hasClass('linecoldiscount')) {
                            col = 'remise_percent';
                        } else if ($(item).hasClass('linecolqty')) {
                            col = 'qty';
                        } else if ($(item).hasClass('linecolmargin1')) {
                            col = 'pa_ht';
                        } else if ($(item).hasClass('linecolcycleref')) {
                            col = 'situation_cycle_ref';
                        } else if ($(item).hasClass('linecoluht_currency')) {
                            col = 'pu_ht_devise';
                        } else if (<?php echo floatval(DOL_VERSION)?> >=
                        21.0 && $(item).hasClass('linecolmargin2')
                    )
                        {

                            col = 'marge_tx';
                        }
                    else
                        if (<?php echo floatval(DOL_VERSION)?> >=
                        21.0 && $(item).hasClass('linecolmark1')
                    )
                        {
                            col = 'marque_tx';

                        }
                    else
                        {
                            col = 'price';
                        }

                        $a = $('<a class="blue" style="text-decoration:underline;cursor:text;" />');
                        $a.attr('href', "javascript:;");
                        $a.attr('value', value);
                        $a.attr('col', col);
                        $a.attr('lineid', lineid);
                        $a.attr('objectid', '<?php echo $object->id; ?>');
                        $a.attr('objectelement', '<?php echo $object->element; ?>');

                        //if(value == '' || value=='&nbsp;') $(item).html('...');

                        $(item).wrapInner($a);

                        $(item).attr('align', 'right');

                        $(item).append('<input type="text" class="flat qcp" name="qcp-price" style="display:none;" size="8" />');

                        $(item).unbind().click(function () {
                            var $link = $(this).find('a');
                            var $input = $(this).find('input.qcp');

                            if ($link.is(':visible')) {
                                $link.hide();
                                $input.show();
                                $input.val($link.attr('value').trim());
                                $input.focus();
                                $input.select();
                            }

                            $input.unbind();
                            $input.keypress(function (evt) {
                                //Deterime where our character code is coming from within the event
                                var charCode = evt.charCode || evt.keyCode;
                                if (charCode == 13) { //Enter key's keycode
                                    $input.blur();

                                    return false;
                                }
                            });

                            $input.blur(function () {
                                var value = $(this).val();
                                var col = $link.attr('col');
                                var lineid = $link.attr('lineid');
                                var objectid = $link.attr('objectid');
                                var objectelement = $link.attr('objectelement');
                                $link.show();
                                $link.html('...');
                                $input.hide();
                                $.ajax({
                                    url: "<?php echo dol_buildpath('/quickcustomerprice/script/interface.php', 1) ?>"
                                    , data: {
                                        put: 'price'
                                        , value: value
                                        , column: col
                                        , lineid: lineid
                                        , objectid: objectid
                                        , objectelement: objectelement
                                    }
                                    , dataType: 'json'
                                }).done(function (data) {
									// DA025943: solution sale en attendant une refonte
									const marginOpts = <?= json_encode([
										getDolGlobalInt('DISPLAY_MARGIN_RATES') ? 'marge_tx' : 0,
										getDolGlobalInt('DISPLAY_MARK_RATES') ? 'marque_tx' : 0])
										?>.filter(opt => opt);
                                    if (data.error == null) {
                                        $('tr[id=row-' + lineid + '] td.linecolht').html(data.total_ht);
                                        $('tr[id=row-' + lineid + '] td.linecolutotalht_currency').html(data.multicurrency_total_ht);
                                        $('tr[id=row-' + lineid + '] td.linecoldiscount a').html((data.remise_percent == 0 || data.remise_percent == '') ? '&nbsp;' : data.remise_percent + '%');
                                        $('tr[id=row-' + lineid + '] td.linecoluht_currency a').html(data.pu_ht_devise);
                                        $('tr[id=row-' + lineid + '] td.linecoluht_currency a').attr('value', data.pu_ht_devise);
                                        $('tr[id=row-' + lineid + '] td.linecolqty a').html(data.qty);
                                        if (<?php echo floatval(DOL_VERSION)?> >=
                                        21.0
                                    )
                                        {
                                            $('tr[id=row-' + lineid + '] td.linecolmargin1 a').attr('value', data.pa_ht);
                                            $('tr[id=row-' + lineid + '] td.linecolmargin2 a').html(data.marge_tx);
                                            $('tr[id=row-' + lineid + '] td.linecolmargin2 a').attr('value', data.marge_tx);
                                            $('tr[id=row-' + lineid + '] td.linecolmark1 a').html(data.marque_tx);
                                            $('tr[id=row-' + lineid + '] td.linecolmark1 a').attr('value', data.marque_tx);
                                        }
                                    else
                                        {
											for (let index = 0; index < marginOpts.length; index++) {
												const marginOpt = marginOpts[index];
												$(`tr[id=row-${lineid}] td.linecolmargin2:eq(${index})`).html(pricejs(parseFloat(data[marginOpt].replace(',', '.'))) + '%');
											}
                                            $('tr[id=row-' + lineid + '] td.linecolmargin2:first').html(data.marge_tx);
                                            $('tr[id=row-' + lineid + '] td.linecolmargin2:eq(1)').html(data.marque_tx);
                                        }
                                        $('tr[id=row-' + lineid + '] td.linecoluht a').html(data.price);
                                        $('tr[id=row-' + lineid + '] td.linecoluht a').attr('value', data.price);
                                        $('tr[id=row-' + lineid + '] td.linecolcycleref a').html(data.situation_cycle_ref + '%');
                                        $('tr[id=row-' + lineid + '] td.linecoluttc').html(data.uttc);
                                        $link.attr('value', data[col]);

                                        //On remplace en direct les montants de la fiche
                                        var url = "<?php echo $_SERVER['PHP_SELF'] ?>?id=" + objectid;

                                        $(".tabBar .fichecenter").load(url + " .tabBar .fichecenter .fichehalfleft, .tabBar .fichecenter .fichehalfright")

                                        // we call the callback functions potentially added by hooks
                                        priceCallbacks.forEach((callback) => {
                                            callback(lineid, data);
                                        });
                                    } else if (data.error == 'updateFailed') {
                                        $('tr[id=row-' + lineid + '] td.linecoluht a').html(data.msg);
                                    }
                                });

                            });

                        });

                    });

                    /*
					 * Extrafields
					 */
                    //Ajout du picto
                    var elements = $("#tablelines").find('[id*=\'extras\']');

					<?php
					$reshook = $hookmanager->executeHooks('excludePictoForElements', $parameters, $object, $action);
					if ($reshook > 0 && is_array($hookmanager->resArray) && ! empty($hookmanager->resArray)){
					?> var excludedElements =  <?php echo json_encode($hookmanager->resArray); ?>;
                    elements = elements.not(function () {
                        var elementID = $(this).attr("id");
                        return excludedElements.some(function (word) {
                            return elementID.includes(word);
                        });
                    });
					<?php
					}?>

                    elements.each(function () {
                        let lineid = $(this).closest('tr').attr('id').substr(4);

                        $a = $('<a class="blue quick-edit-extras" style="cursor:pointer;" />');
                        $a.attr('href', 'javascript:;');
                        $a.attr('lineid', lineid);
                        $a.attr('objectid', '<?php echo $object->id; ?>');
                        $a.attr('objectelement', '<?php echo $object->element; ?>');
                        $a.html('<?php echo img_edit(); ?>');


                        $(this).after($a);
                        $(this).after("&nbsp;&nbsp;&nbsp;");

                    });
                    //On affiche l'input
                    $(".quick-edit-extras").on('click', function () {


                        let spanToEdit = $(this).prev('div');

                        if (spanToEdit.length == 0) spanToEdit = extraTd;
                        let TClassExtra = spanToEdit.attr('class').split(' ');
                        for (let i = 0; i < TClassExtra.length; i++) {
                            if (TClassExtra[i].includes('extras')) TClassExtra = TClassExtra[i].split('_');
                        }

                        for (let i = 0; i < TClassExtra.length; i++) {
                            if (i == 0 || i == 1) continue;
                            else if (i == 2 && TClassExtra[2] != 'extras' || i == 3 && TClassExtra[2] == 'extras') extrafieldCode = TClassExtra[i];
                            else if (i == 2) continue;
                            else extrafieldCode += '_' + TClassExtra[i];
                        }
						<?php if (floatval(DOL_VERSION) >= 17) { ?>
                        if (extrafieldCode.indexOf(' ') >= 0) {// Si on a des espaces
                            let TCode = extrafieldCode.split(' ');
                            extrafieldCode = TCode[0];
                        }
						<?php } ?>

                        let lineid = $(this).attr('lineid');
                        let objectelement = $(this).attr('objectelement');
                        $.ajax({
                            url: "<?php echo dol_buildpath('/quickcustomerprice/script/interface.php', 1) ?>"
                            , data: {
                                get: 'extrafield-value'
                                , code_extrafield: extrafieldCode
                                , lineid: lineid
                                , objectelement: objectelement
                            }
                            , dataType: 'html'
                        }).done(function (data) {
                            spanToEdit.html(data);
                        });
                        $(this).hide();
                    });

                    //On met à jour l'input
                    $(document).on('click', '.quickSaveExtra', function () {
                        let value = '';
                        let extrafieldCode = $(this).attr('extracode');
                        let lineid = $(this).attr('lineid');
                        let type = $(this).attr('type');
                        let lineclass = $(this).attr('lineclass');
                        let spanToEdit = $(this).closest('[id*="extras"]');
                        $(this).closest('td').find('.quick-edit-extras').show();
                        //le cas d'un input type text classique
                        if (type == 'varchar'
                            || type == 'int'
                            || type == 'price'
                            || type == 'phone'
                            || type == 'mail'
                            || type == 'phone'
                            || type == 'url'
                            || type == 'password') {

                            let siblings = $(this).siblings('input');
                            value = $(siblings[0]).val();

                        }

                        if (type == 'link') {
                            siblings = $(this).siblings('select');
                            value = $(siblings[0]).val();
                        }

                        //Wysiwyg
                        if (type == 'text') {
                            elem = $(this).siblings('div')[0];
                            iframe = $(elem).find("iframe")[0];
                            value = (iframe.contentWindow.document.body.innerHTML);
                        }
                        /*
						 * Type date
						 */
                        if (type == 'date' || type == 'datetime') {
                            let hour = '00';
                            let min = '00';
                            let day = $($(this).siblings('input[name*="day"]')[0]).val();
                            let month = $($(this).siblings('input[name*="month"]')[0]).val();
                            let year = $($(this).siblings('input[name*="year"]')[0]).val();
                            if ($(this).siblings('select[name*=hour]').length > 0) {
                                hour = $($(this).siblings('select[name*=hour]')[0]).val();
                                min = $($(this).siblings('select[name*=min]')[0]).val();
                            }
                            if (year != '') {
                                let date = new Date(year, month - 1, day, hour, min);
                                value = Math.floor(date / 1000);
                            }
                        }
                        /*
                         * Boolean
                         */
                        if (type == 'boolean') {
                            if ($($(this).siblings('input')[0]).is(':checked')) value = 1;
                            else value = 0;
                        }
                        /*
                         * Select
                         */
                        if (type == 'select' || type == 'sellist') {
                            value = $($(this).siblings('select')[0]).val();
                        }
                        /*
						* Multiselect
						*/
                        if (type == 'checkbox' || type == 'chkbxlst') {
                            value = $($(this).siblings('select')[0]).select2('val');
                        }
                        /*
                         * Radio
                         */
                        if (type == 'radio') {
                            value = $(this).siblings('input:checked').val();
                        }

                        $.ajax({
                            url: "<?php echo dol_buildpath('/quickcustomerprice/script/interface.php', 1) ?>"
                            , data: {
                                put: 'extrafield-value'
                                , code_extrafield: extrafieldCode
                                , type: type
                                , lineid: lineid
                                , lineclass: lineclass
                                , value: value
                            }
                            , dataType: 'html'
                        }).done(function (data) {
                            spanToEdit.html(data);
                        });

                    });

                });

            </script>

			<?php
		}

		if (! $error) {
			return 0; // or return 1 to replace standard code
		} else {
			return -1;
		}
	}

	private function _getTIDLinesToChange($object) {
		$TRes = array();

		if (! empty($object->lines)) {
			foreach ($object->lines as $line) {
				if (($line->info_bits & 2) != 2) { // On empêche l'édition des lignes issues d'avoirs et de d'acomptes
					$TRes[] = $line->id;
				}
			}
		}

		if ($object->element == 'propal' && ! empty($object->line)) { // New propal line
			$TRes[] = strval($object->line->id);
		}

		return $TRes;
	}
}
