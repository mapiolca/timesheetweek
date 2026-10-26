"""Check template bodies and execute the activation migration using SQLite.

The migration SQL is evaluated by PHP from the descriptor, then executed against
fixture rows. SQLite exercises the common REPLACE/INSTR SQL used here; this does
not replace a real Dolibarr/MySQL activation test.
"""

import json
import os
from pathlib import Path
import re
import sqlite3
import subprocess
import unittest


ROOT = Path(__file__).resolve().parents[1]
SIGNATURES = ("__SENDEREMAIL_SIGNATURE__", "__USER_SIGNATURE__")


def activation_sql(prefix, entity):
    source = (ROOT / "core/modules/modTimesheetWeek.class.php").read_text(encoding="utf-8")
    block = re.search(
        r"(?ms)^\t\t\$sqlRemoveNotificationSignatures = .*?"
        r"^\t\t\$sql\[\] = \$sqlRemoveNotificationSignatures;",
        source,
    )
    if block is None:
        raise AssertionError("The activation migration for notification signatures is missing.")
    if source.index(block.group()) > source.index("$resultInit = $this->_init($sql, $options);"):
        raise AssertionError("The migration must run before native notification mirrors are synchronized.")
    script = (
        "define('MAIN_DB_PREFIX', " + json.dumps(prefix) + ");"
        "$conf = (object) array('entity' => " + str(entity) + "); $sql = array();"
        + block.group()
        + "echo json_encode($sql, JSON_THROW_ON_ERROR);"
    )
    result = subprocess.run(["php", "-r", script], check=True, text=True, capture_output=True)
    return json.loads(result.stdout)[0]


class NotificationSignatureTest(unittest.TestCase):
    def test_bundled_bodies(self):
        for language in ("fr_FR", "en_US", "de_DE", "es_ES", "it_IT"):
            values = {}
            for line in (ROOT / "langs" / language / "timesheetweek.lang").read_text(encoding="utf-8").splitlines():
                if "=" in line and not line.lstrip().startswith("#"):
                    key, value = line.split("=", 1)
                    values[key.strip()] = value.strip()
            keys = ["TimesheetWeekNativeNotificationDefaultBody"] + [
                "TimesheetWeekTemplate" + reason + "Body"
                for reason in ("Submit", "Approve", "Refuse", "Setdraft", "Seal", "Unseal")
            ]
            for key in keys:
                with self.subTest(language=language, template=key):
                    body = values[key]
                    for signature in SIGNATURES:
                        self.assertNotIn(signature, body)
                    self.assertIn("__TIMESHEETWEEK_ACCESS__", body)
            self.assertIn("__ACTION_USER_FULLNAME__", values["TimesheetWeekTemplateApproveBody"])
            self.assertIn("__TIMESHEETWEEK_MOTIF__", values["TimesheetWeekTemplateRefuseBody"])

    def test_existing_templates_and_mirrors(self):
        signed = (
            '<p>Approved by __ACTION_USER_FULLNAME__.</p>'
            '<a href="__TIMESHEETWEEK_URL_RAW__">Open</a>'
            '<p>__SENDEREMAIL_SIGNATURE__</p><div>__USER_SIGNATURE__</div>'
            '<footer>__MYCOMPANY_NAME__ __TIMESHEETWEEK_MAIL_SIGNATURE__</footer>'
        )
        cleaned = signed.replace(SIGNATURES[0], "").replace(SIGNATURES[1], "")
        migrated_body = None
        for prefix in ("llx_", "test_long_prefix_"):
            for entity, changed_ids in ((1, {1, 2, 4, 5}), (3, {1, 3, 4, 5})):
                with self.subTest(prefix=prefix, entity=entity):
                    db = sqlite3.connect(":memory:")
                    self.addCleanup(db.close)
                    table = prefix + "c_email_templates"
                    db.execute(
                        f"CREATE TABLE {table} (rowid INTEGER PRIMARY KEY, entity INTEGER, module TEXT, "
                        "type_template TEXT, content TEXT, content_lines TEXT, topic TEXT, email_to TEXT)"
                    )
                    definitions = (
                        (1, 0, "timesheetweek", "timesheetweek@timesheetweek", signed),
                        (2, 1, "timesheetweek", "timesheetweek@timesheetweek", signed),
                        (3, 3, "timesheetweek", "timesheetweek@timesheetweek", signed),
                        (4, 0, None, "timesheetweek@timesheetweek", signed),
                        (5, 0, "", "timesheetweek@timesheetweek", signed),
                        (6, 0, "timesheetweek", "actioncomm_send", signed),
                        (7, 0, "othermodule", "timesheetweek@timesheetweek", signed),
                        (8, 0, "othermodule", "othermodule_send", signed),
                        (9, 2, "timesheetweek", "timesheetweek_send", signed),
                        (10, entity, "timesheetweek", "timesheetweek@timesheetweek", cleaned),
                        (11, entity, "timesheetweek", "timesheetweek@timesheetweek", None),
                        (12, entity, "timesheetweek", "timesheetweek_send", signed),
                        (13, 0, "timesheetweek", "timesheetweek_send", signed),
                        (14, entity, "timesheetweek", "unrelated_type", signed),
                    )
                    changed_ids |= {12, 13}
                    rows = [row + ("Custom line body", "Custom subject", "recipient@example.com") for row in definitions]
                    db.executemany(f"INSERT INTO {table} VALUES (?, ?, ?, ?, ?, ?, ?, ?)", rows)
                    migration = activation_sql(prefix, entity)
                    db.execute(migration)
                    expected = [
                        row[:4] + ((cleaned if row[0] in changed_ids else row[4]),) + row[5:]
                        for row in rows
                    ]
                    actual = db.execute(f"SELECT * FROM {table} ORDER BY rowid").fetchall()
                    self.assertEqual(expected, actual)
                    changed_count = db.total_changes
                    db.execute(migration)
                    self.assertEqual(changed_count, db.total_changes, "A second activation must not update templates again.")
                    self.assertEqual(actual, db.execute(f"SELECT * FROM {table} ORDER BY rowid").fetchall())
                    migrated_body = actual[0][4]

        self.assertIsInstance(migrated_body, str)
        environment = dict(os.environ, TIMESHEETWEEK_TEST_MIGRATED_BODY=migrated_body)
        result = subprocess.run(
            ["php", str(ROOT / "tests/NotificationHookIsolationTest.php")],
            env=environment, check=True, text=True, capture_output=True,
        )
        self.assertIn("Notification hook isolation test passed.", result.stdout)


if __name__ == "__main__":
    unittest.main()
