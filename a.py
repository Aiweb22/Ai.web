import socket
import threading
import time

TARGET_IP = "ip"
UDP_PORT = port
PACKETS = 1000
THREADS = 10

def tcp_server():
    s = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
    s.bind((TARGET_IP, TCP_PORT))
    s.listen()
    while True:
        conn, addr = s.accept()
        conn.recv(1024)
        conn.close()

def udp_server():
    s = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
    s.bind((TARGET_IP, UDP_PORT))
    while True:
        s.recvfrom(1024)

def tcp_client():
    for _ in range(PACKETS):
        try:
            s = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
            s.connect((TARGET_IP, TCP_PORT))
            s.send(b"TCP")
            s.close()
        except:
            pass

def udp_client():
    for _ in range(PACKETS):
        try:
            s = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
            s.sendto(b"Hello UDP", (TARGET_IP, UDP_PORT))
            s.close()
        except:
            pass

for _ in range(2):
    threading.Thread(target=tcp_server, daemon=True).start()
    threading.Thread(target=udp_server, daemon=True).start()

time.sleep(1)

threads = []
for _ in range(THREADS):
    t1 = threading.Thread(target=tcp_client)
    t2 = threading.Thread(target=udp_client)
    threads.append(t1)
    threads.append(t2)
    t1.start()
    t2.start()

for t in threads:
    t.join()

print("Wadinoo")
