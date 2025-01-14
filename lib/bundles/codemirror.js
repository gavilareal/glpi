/**
 * ---------------------------------------------------------------------
 *
 * GLPI - Gestionnaire Libre de Parc Informatique
 *
 * http://glpi-project.org
 *
 * @copyright 2015-2025 Teclib' and contributors.
 * @copyright 2003-2014 by the INDEPNET Development Team.
 * @licence   https://www.gnu.org/licenses/gpl-3.0.html
 *
 * ---------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of GLPI.
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
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 * ---------------------------------------------------------------------
 */

// Codemirror base lib
require('codemirror');
require('codemirror/lib/codemirror.css');

// Codemirror addons
require('codemirror/addon/fold/foldcode.js');
require('codemirror/addon/fold/brace-fold.js');
require('codemirror/addon/fold/comment-fold.js');
require('codemirror/addon/fold/foldgutter.js');
require('codemirror/addon/hint/show-hint.js');
require('codemirror/addon/hint/css-hint.js');
require('codemirror/addon/runmode/runmode.js');

require('codemirror/addon/fold/foldgutter.css');
require('codemirror/addon/hint/show-hint.css');

// Codemirror language modes
require('codemirror/mode/css/css.js');
require('codemirror/mode/sql/sql.js');
require('codemirror/mode/javascript/javascript.js');
