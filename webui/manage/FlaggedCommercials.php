<?php

class FlaggedCommercials implements ManageCard {
    private $name = 'Flagged Commercials';
    private $links = [];
    private $html = '';
    private $mysqli = null;

    public function setMysqli($mysqli) {
        $this->mysqli = $mysqli;
    }

    public function name() { 
        return $this->name; 
    }

    public function links() { 
        return $this->links; 
    }

    public function display() { 
        return 'wide'; 
    }

    public function priority() { 
        return 1; 
    }

    public function html() {
        $items = [];
        if ($this->mysqli) {
            $res = $this->mysqli->query("SELECT id, name FROM commercials WHERE flag != 0 ORDER BY id DESC");
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $items[] = [
                        'id'   => $row['id'],
                        'name' => stripslashes($row['name'])
                    ];
                }
            }
        }

        $rows_html = '';
        if (empty($items)) {
            $rows_html = '<tr><td style="text-align: center; color: var(--text-muted); padding: 1rem;">Nothing to see here</td></tr>';
        } else {
            $count = 0;
            foreach ($items as $v) {
                $count++;
                $name_escaped = htmlspecialchars($v['name']);
                $name_encoded = urlencode($v['name']);
                $name_js = addslashes($v['name']);

                $rows_html .= '
                <tr id="manComm_' . $count . '">
                    <td style="padding: 0.75rem 1rem;"><a href="/?video=' . $name_encoded . '" target="_blank">' . $name_escaped . '</a></td>
                    <td style="text-align: right; display: flex; gap: 8px; justify-content: flex-end; padding: 0.75rem 1rem;">
                        <button class="type-badge" style="cursor: pointer; border: none;" onclick="unflagCommercial(' . (int)$v['id'] . ', \'manComm_' . $count . '\');">unflag</button>
                        <button class="type-badge" style="cursor: pointer; border: none; background: var(--accent);" onclick="renameVideo(\'' . $name_js . '\');">rename</button>
                    </td>
                </tr>';
            }
        }

        return '
        <div class="card-table-wrapper" style="border: none; border-radius: 0;">
            <table style="width: 100%; border-collapse: collapse;">
                <tbody>
                    ' . $rows_html . '
                </tbody>
            </table>
        </div>';
    }
}