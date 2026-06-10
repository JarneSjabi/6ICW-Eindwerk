# Platform autonome mobiliteit

Eindwerk Jarne Verlinden - 6ICW

## Deployment:

First /admin, then /backend, afterwards you can open frontend by opening the HTML file in your browser (if needed enable Incognito or Private mode to fix potential CORS issues)

/admin:
- Install PHP & SQL
- Create SQL DB according to eindwerk.sql scheme
- Run using: php -S localhost:80

/backend:
- CD into
- Create .venv
- pip -r install requirements.txt
- Run: python run_before_demo.py
- Run: python main.py

/frontend:
- Just open in your browser (if needed enable Incognito or Private mode to fix potential CORS issues)
- Then you can open the admin panel too on http://localhost/
- Create a new user account in the DB (PWD hash using bcrypt generator online)
- Then sign in

(Dev notes):

A useful query to see how many VLR's each vehicle has currently:

SELECT vehicle_id, COUNT(*) AS report_count
FROM vehicle_location_reports
GROUP BY vehicle_id
ORDER BY report_count DESC;

User: admin, admin