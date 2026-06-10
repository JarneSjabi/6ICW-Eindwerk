"""
Seed script for vehicle_location_reports.

Deletes all rows from `vehicle_location_reports` and inserts a single
location report for the configured vehicle ID.

Usage:
  - Edit the constants below (`VEHICLE_ID`, `LATITUDE`, `LONGITUDE`) or
    set `SEED_VEHICLE_ID`, `SEED_LATITUDE`, `SEED_LONGITUDE` environment
    variables to override.

Run:
    python run_before_demo.py
"""
import os 
import sys 
from database import Database 

# ADDRESSES:
# Start: Monnikheide 1
# End: Eikevlietbaan 56

VEHICLE_ID =int (os .getenv ('SEED_VEHICLE_ID', '1'))
DELETE_ALL_OTHERS = True

# 51.0756103,4.2741636 | CC Binder
LATITUDE =float (os .getenv ('SEED_LATITUDE','51.0756103'))
LONGITUDE =float (os .getenv ('SEED_LONGITUDE','4.2741636'))


def main ():
    db =Database ()
    if not db .ensure_live ():
        print ("Failed to connect to database")
        sys .exit (2 )


    if DELETE_ALL_OTHERS == True:
        delete_q ="DELETE FROM vehicle_location_reports"
        deleted =db .execute_update (delete_q )
        print (f"Deleted vehicle_location_reports rows result: {deleted }")


    insert_q =(
    "INSERT INTO vehicle_location_reports (vehicle_id, latitude, longitude, reported_at)"
    " VALUES (%s, %s, %s, NOW())"
    )
    res =db .execute_update (insert_q ,(VEHICLE_ID ,LATITUDE ,LONGITUDE ))
    print (f"Inserted vehicle_location_reports result (last_id or affected_rows): {res }")


if __name__ =='__main__':
    main ()
