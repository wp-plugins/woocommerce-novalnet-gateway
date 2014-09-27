<?php
#########################################################
#  This file is used for unsetting autofill fields      #
#  for form payment types.                              #
#             											#
#  Released under the GNU General Public License.       #
#  This free contribution made by request.              #
#  If you have found this script useful a small         #
#  recommendation as well as a comment on merchant form #
#  would be greatly appreciated.                        #
#                                                       #
#  Script : nn_unset.php                                #
#                                                       #
#########################################################

if (!isset($_SESSION))
	session_start();

if(isset($_POST['clr_session'])){
	if($_POST['clr_session'] == 1) {

		if(isset($_SESSION['cc'])) unset($_SESSION['cc']);
		if(isset($_SESSION['sepa'])) unset($_SESSION['sepa']);
	}
}
?>