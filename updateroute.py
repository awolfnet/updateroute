#!/usr/bin/env python

import re
import urllib2
import sys
import math
import textwrap
import os
import time

gw='121.127.234.254'
routes_source=r'http://ftp.apnic.net/apnic/stats/apnic/delegated-apnic-latest'

def writelog(content):
    #logfile=open('/var/log/getroutes.log','w+')
    line=time.strftime('%Y-%m-%d %H:%M:%S',time.localtime(time.time())) + " > " + content 
    print line
    #logfile.write(line)
    #logfile.close()

if __name__=='__main__':
    
    writelog("Default gateway:"+gw)
    writelog("Routes source:"+routes_source)
    
    #fetch data from apnic
    writelog("Fetching data from apnic.net: " + routes_source)

    url=routes_source
    data=urllib2.urlopen(url).read()

    writelog("Fetching done.")

    regex=re.compile(r'apnic\|hk\|ipv4\|[0-9\.]+\|[0-9]+\|[0-9]+\|a.*',re.IGNORECASE)
    routes=regex.findall(data)

    results=[]

    for item in routes:
        unit_items=item.split('|')
        starting_ip=unit_items[3]
        num_ip=int(unit_items[4])
        
        imask=0xffffffff^(num_ip-1)
        #convert to string
        imask=hex(imask)[2:]
        mask=[0]*4
        mask[0]=imask[0:2]
        mask[1]=imask[2:4]
        mask[2]=imask[4:6]
        mask[3]=imask[6:8]
        
        #convert str to int
        mask=[ int(i,16 ) for i in mask]
        mask="%d.%d.%d.%d"%tuple(mask)
        
        #mask in *nix format
        mask2=32-int(math.log(num_ip,2))
        
        results.append((starting_ip,mask,mask2))

    count=len(results)
    writelog("Fetched routes:%s"%(count))
    
    if count<=0 :
        write("Fetched nothing.")
        exit(0)
    
    for item in os.popen('ip route list proto apnic'):
        net=item.split(' ')[0]
        writelog("Deleting local route:%s"%(net))
        os.system('ip route del %s'%(net))

    for ip,mask,maskbit in results:
        writelog("Adding route net:%s/%s"%(ip,maskbit))
        os.system('ip route add %s/%s via %s proto apnic'%(ip,maskbit,gw))

    writelog("Done.")
    exit(1)
