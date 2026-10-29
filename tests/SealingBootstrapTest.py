"""Exercise the preview's real web entry point with a minimal Dolibarr fixture.

The fixture stops inside main.inc.php; this checks environment discovery only,
not authentication, notification rendering or a complete Dolibarr installation.
"""

from pathlib import Path
import shutil
import socket
import subprocess
import tempfile
import time
import unittest
from urllib.error import URLError
from urllib.request import urlopen


ROOT = Path(__file__).resolve().parents[1]


class SealingBootstrapTest(unittest.TestCase):
    def preview(self, custom, main_available=True):
        with tempfile.TemporaryDirectory(prefix="timesheetweek-bootstrap-", dir=ROOT / "tests") as folder:
            webroot = Path(folder) / "htdocs"
            module = webroot / "custom" / "timesheetweek" if custom else webroot / "timesheetweek"
            tests = module / "tests"
            tests.mkdir(parents=True)
            shutil.copy2(ROOT / "tests/SealingNotificationTest.php", tests)
            if main_available:
                (webroot / "main.inc.php").write_text(
                    "<?php exit('DOLIBARR_ENVIRONMENT_LOADED');\n", encoding="utf-8", newline="\n"
                )
            with socket.socket() as listener:
                listener.bind(("127.0.0.1", 0))
                port = listener.getsockname()[1]
            with tempfile.TemporaryFile(dir=ROOT / "tests") as log:
                server = subprocess.Popen(
                    ["php", "-d", "display_errors=1", "-S", f"127.0.0.1:{port}", "-t", str(webroot)],
                    stdout=log, stderr=log,
                )
                try:
                    prefix = "/custom" if custom else ""
                    url = f"http://127.0.0.1:{port}{prefix}/timesheetweek/tests/SealingNotificationTest.php"
                    for attempt in range(100):
                        if server.poll() is not None:
                            log.seek(0)
                            self.fail(log.read().decode("utf-8", errors="replace"))
                        try:
                            with urlopen(url, timeout=2) as response:
                                return response.read().decode("utf-8")
                        except URLError:
                            if attempt == 99:
                                raise
                            time.sleep(0.05)
                finally:
                    server.terminate()
                    server.wait(timeout=10)

    def test_module_in_htdocs(self):
        self.assertEqual(self.preview(custom=False), "DOLIBARR_ENVIRONMENT_LOADED")

    def test_module_in_custom(self):
        self.assertEqual(self.preview(custom=True), "DOLIBARR_ENVIRONMENT_LOADED")

    def test_missing_environment_stops_cleanly(self):
        self.assertEqual(self.preview(custom=True, main_available=False), "Include of main fails")


if __name__ == "__main__":
    unittest.main()
