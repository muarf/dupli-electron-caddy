import os
import sys
import unittest

class TestInstallPipeline(unittest.TestCase):
    def test_install_script_exists(self):
        """Vérifie la présence du script d'installation install.py"""
        script_dir = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
        target_script = os.path.join(script_dir, "install.py")
        self.assertTrue(os.path.exists(target_script), "Le script install.py doit être présent")

if __name__ == "__main__":
    unittest.main()
