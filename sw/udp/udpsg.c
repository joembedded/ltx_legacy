/*****************************************************************************
* udpsg.c - - UDPServerGeneral, UDP Server Multi Plattform - (C)Joembedded.de
*
* Project Source: https://github.com/joembedded/UDPServerGeneral
* 
* For performance reasons, each UDP packet is not processed individually,
* Instead, up to MAX_CLIENTS packets are read at once.
* This allows a subsequent libCurl call to use the 'curl_multi_init'
* feature ( https://curl.se/ -> libcurl ). A faster
* solution would be a single libCurl call with the data as an array.
* But this would make the called script more complicated. Therefore
* the simple version with 'curl_multi'.
*
*
* UDPServerGeneral works with Windows (MS VS) ans Linux (GCC).
*
* Windows: For MS VS 'libcurl.dll' is required:
* Install via vcpk (see https://github.com/curl/curl/blob/master/docs/INSTALL.md#building-using-vcpkg ):
*   git clone https://github.com/Microsoft/vcpkg.git
*   cd vcpkg
*   bootstrap-vcpkg.sh
*   vcpkg integrate install
*   vcpkg install curl[tool]
* Takes about 5 minutes, then libcurl can be used as usual (exactly identical
* to gcc! No further include or libs necessary!)
*
* Linux:
*   Libcurl: apt install libcurl4-openssl-dev -y
*   Link via 'gcc -lcurl'
*   Directory e.g.: cd /var/www/vhosts/joembedded.eu/httpdocs/ltx/sw/udp
*   Compile: gcc udpsg.c -o udpsg -lcurl
*
* Test with e.g:
*    nc -u localhost 5288 -v
*    nc -u joembedded.eu 5288 -v
* For Tests run manually on console (set DEBUG 1),
* for productin (mainly on LINUX) install as Service.
*
****************************************************/

#define VERSION  "V1.10 16.12.2023"

#ifdef _MSC_VER	// MS VC
#define _CRT_SECURE_NO_WARNINGS
#define _WINSOCK_DEPRECATED_NO_WARNINGS
#pragma comment(lib,"ws2_32.lib") //Winsock Library
#else // GCC
typedef int SOCKET;
#endif

#include <errno.h>
#include <stdio.h> 
#include <stdint.h>
#include <stdlib.h> 
#include <string.h> 
#include <time.h>

#ifdef _MSC_VER	// MS VS
#include<winsock2.h>
#else // GCC
#include <sys/types.h> 
#include <sys/socket.h> 
#include <arpa/inet.h> 
#include <unistd.h> 
#include <fcntl.h>
#endif

#include "curl/curl.h"

//-----------------------------------------
#define UDP_PORT     5288	// UDP Listen/Send Default Port
// Default-Script Windows/Linux - Hex-Payload will be added:
#ifdef _MSC_VER	// MS VS
#define CALLSCRIPT "http://localhost/ltx/sw/udp/payload_minimal.php?p=" 
#else // GCC
#define CALLSCRIPT "http://joembedded.eu/ltx/sw/udp/payload_minimal.php?p="
#endif
#define CALLSCRIPT_MAXRUN_MS	3000 // Recommended: <5sec
#define MAX_CLIENTS	10		// Maximum Number to UPD similar
//-----------------------------------------

#define URL_MAXLEN 160	// Chars
#define RX_BYTE_BUFLEN 1024  // Binary - UDP pakets are <= 1k by default
#define TX_HEX2CHAR_BUFLEN 2048  // Chars - 1k Bin in 2k chars

#define IDLE_TIME 60 // Server Loop Idle-Timeout 60 sec

int _verbose = 0;

SOCKET sockfd = (SOCKET)0;	// Server, local Port
int udp_port = UDP_PORT;	
char callscript[URL_MAXLEN] = CALLSCRIPT;

typedef struct {
	struct sockaddr_in client_socket;	// Source
	char rx_buffer[RX_BYTE_BUFLEN + 1];  // OPt. String with 0-Terminator (Binary)
	int rcv_len; // Receives len
	char tx_replybuf[TX_HEX2CHAR_BUFLEN + 1];
	int tx_len;	// Transmit len
} CLIENT;

CLIENT clients[MAX_CLIENTS];
int client_sock_len = sizeof(struct sockaddr_in);  //len is value/result 

// Init Socket - Return 0 if OK, Errors: All != 0
int init_udp_server_socket(void) {
	// Filling server information - Common for MSC / GCC
	struct sockaddr_in server_socket;
	memset(&server_socket, 0, sizeof(server_socket));
	server_socket.sin_family = AF_INET;
	server_socket.sin_addr.s_addr = INADDR_ANY;
	server_socket.sin_port = htons(udp_port); // host2network

#ifdef _MSC_VER	// MS VS only
	WSADATA wsa;
	if (WSAStartup(MAKEWORD(2, 2), &wsa) != 0) return WSAGetLastError();  // use V2.2
	if ((sockfd = socket(AF_INET, SOCK_DGRAM, 0)) == INVALID_SOCKET) return WSAGetLastError();
	if (bind(sockfd, (struct sockaddr*)&server_socket, sizeof(server_socket)) == SOCKET_ERROR) return WSAGetLastError();
	u_long iMode = 1; // If iMode != 0, non-blocking mode is enabled.
	int res = ioctlsocket(sockfd, FIONBIO, &iMode);
	if (res != NO_ERROR) return WSAGetLastError();

#else // GCC
	if ((sockfd = socket(AF_INET, SOCK_DGRAM, 0)) < 0) return -1; // -1 Invalid (-2: Options)
	if (bind(sockfd, (const struct sockaddr*)&server_socket, sizeof(server_socket)) < 0) return -3; // -3 Bind
	if (fcntl(sockfd, F_SETFL, O_NONBLOCK) < 0) return -4; // Make it non-Blocking
#endif
	return 0;
}

// Receivce Data from Socket OK: >0: Return LEN (0-Terminated), else Error
int receive_from_udp_socket(int idx) {
	CLIENT* pcli = &clients[idx];
	pcli->rcv_len = 0;
	memset(&pcli->client_socket, 0, client_sock_len);

#ifdef _MSC_VER	// MS VS
	int rcv_len = recvfrom(sockfd, pcli->rx_buffer, RX_BYTE_BUFLEN, 0, (struct sockaddr*)&pcli->client_socket, &client_sock_len);
	if (rcv_len == SOCKET_ERROR) {
		int lastError = WSAGetLastError();
		if (lastError == WSAETIMEDOUT) 	return 0; //printf("(Timeout)\n");
		if (lastError == WSAEWOULDBLOCK) return 0; //printf("(NoData)\n");
		if (lastError == WSAECONNRESET) return 0; //printf("((SocketReset(10054))\n"); // only TCP?
		if (lastError > 0) lastError = -lastError;
		return lastError;
	}
#else // GCC
	int rcv_len = recvfrom(sockfd, pcli->rx_buffer, RX_BYTE_BUFLEN, 0, (struct sockaddr*)&pcli->client_socket, &client_sock_len);
	if (rcv_len == -1) return 0; //printf("(Timeout)\n");
	else if (rcv_len < 0) return rcv_len;

#endif
	if (_verbose) {
		printf("Received %d Bytes from %s:%d\n", rcv_len, inet_ntoa(pcli->client_socket.sin_addr), ntohs(pcli->client_socket.sin_port));
	}
	pcli->rx_buffer[rcv_len] = 0; // Important if Strings used!
	pcli->rcv_len = rcv_len;
	return rcv_len;
}


int wait_for_udp_data(void) {
	// Clearing and setting the recieve descriptor
	fd_set recvsds;
	FD_ZERO(&recvsds);
	FD_SET(sockfd, &recvsds);

	struct timeval timeout;
	timeout.tv_sec = IDLE_TIME; // Set Struct each call
	timeout.tv_usec = 0;

#ifdef _MSC_VER	// MS VS
	int selpar0 = 0; /*Parameter ignored on MS VC */
#else // GCC
	int selpar0 = sockfd + 1; /*Parameter only for GCC */
#endif
	int res = select(selpar0, &recvsds, NULL, NULL, &timeout); // See Pocket Guide to TCP/IP Sockets
#ifdef _MSC_VER	// MS VS
	if (res == SOCKET_ERROR) {
		int lastError = WSAGetLastError();
		if (lastError > 0) lastError = -lastError;
		return lastError;
	}
#endif
	return res;
}

// Send Data, if OK: 0, else Error
int send_reply_to_udp_client(CLIENT* pcli, char* txbuf, int tx_len) {
	int res;
#ifdef _MSC_VER	// MS VS
	if (sendto(sockfd, txbuf, tx_len, 0, (struct sockaddr*)&pcli->client_socket, client_sock_len) == SOCKET_ERROR) res = WSAGetLastError();
	else res = 0;
#else // GCC
	res = sendto(sockfd, txbuf, tx_len, 0, (const struct sockaddr*)&pcli->client_socket, client_sock_len);
	if (res == tx_len) res = 0;
#endif
	return res;
}

// Cleanup - No Return
void close_udp_server_socket(void) {
#ifdef _MSC_VER	// MS VS only
	if (sockfd) closesocket(sockfd);
	WSACleanup();
#else // GCC
	if (sockfd) close(sockfd);
#endif
}

// --CURL Handlers--
static size_t curl_write_cb(char* data, size_t n, size_t len, void* userp)
{
	size_t realsize = n * len; // n: 1.d.R. 1
	CLIENT* pcli = (CLIENT*)userp;
	int hlen = pcli->tx_len;
	size_t maxcopy = TX_HEX2CHAR_BUFLEN - hlen;
	if (maxcopy) { // limit to Maximum. Ignore Ovderdue
		if (realsize <= maxcopy) maxcopy = realsize;
		memcpy(&(pcli->tx_replybuf[hlen]), data, maxcopy); // DSn
		hlen += (int)maxcopy;
		pcli->tx_replybuf[hlen] = 0;
		pcli->tx_len = hlen;
	}
	return realsize;
}

// Return 0 without Errors
int run_curl(int anz) {
	CURLM* cm;
	CURLMsg* msg;

	char url[URL_MAXLEN + (RX_BYTE_BUFLEN * 2) + 1];
	int cp_err = 0; // Curl Process Errors (Cnt)

	curl_global_init(CURL_GLOBAL_ALL);
	cm = curl_multi_init();
	/* Limit the amount of simultaneous connections curl should allow: */
	curl_multi_setopt(cm, CURLMOPT_MAXCONNECTS, (long)anz);
	for (int ridx = 0; ridx < anz; ridx++) {
		CLIENT* pcli = &clients[ridx];
		// Build URL (GET)
		char* pc = url + sprintf(url, "%s", callscript);
		for (int di = 0; di < pcli->rcv_len; di++) {
			uint8_t c = (pcli->rx_buffer[di]) & 255;
			*pc++ = "0123456789abcdef"[(c >> 4)];
			*pc++ = "0123456789abcdef"[(c&15)];
		}
		*pc = 0;
		pcli->tx_len = 0;	// No Reply
		pcli->tx_replybuf[0] = 0;
		if (_verbose) {
			printf("Add URL(Idx:%d):'%s'\n", ridx, url); // Control
		}
		CURL* eh = curl_easy_init();
		curl_easy_setopt(eh, CURLOPT_URL, url);
		curl_easy_setopt(eh, CURLOPT_WRITEFUNCTION, curl_write_cb);
		// Security Simplifications
		curl_easy_setopt(eh, CURLOPT_FOLLOWLOCATION, 1L); // Follow Redirections
		curl_easy_setopt(eh, CURLOPT_SSL_VERIFYPEER, 0L); // No Verify
		curl_easy_setopt(eh, CURLOPT_SSL_VERIFYHOST, 0L); // No Verify
		curl_easy_setopt(eh, CURLOPT_TIMEOUT_MS, CALLSCRIPT_MAXRUN_MS); // Script must complete fast
		curl_easy_setopt(eh, CURLOPT_WRITEDATA, pcli);
		curl_easy_setopt(eh, CURLOPT_PRIVATE, pcli);
		curl_multi_add_handle(cm, eh);
	}

	char bin_reply[(TX_HEX2CHAR_BUFLEN / 2) ]; // Binary Buffer
	for (;;) {
		int cactive = 0; // Connections active
		curl_multi_perform(cm, &cactive);
		int msgs_left = -1; // Dummy
		while ((msg = curl_multi_info_read(cm, &msgs_left))) {
			if (msg->msg == CURLMSG_DONE) { // Currently only CURLMSG_DONE defined by Curl
				CURL* e = msg->easy_handle;
				CLIENT* pcli;
				curl_easy_getinfo(e, CURLINFO_PRIVATE, (void*)&pcli);
				int cres = msg->data.result; // 0:OK, e.g. 28:Timeout
				if (!cres) {
					char* phex = pcli->tx_replybuf;
					char* pbin = bin_reply;
					int tanz = pcli->tx_len;
					while (tanz) {
						char ch = *phex++;
						if ((ch < '0' || ch > '9') && (ch < 'a' || ch > 'f') && (ch < 'A' || ch > 'F')) break;
						if (ch >= 'a') ch -= ('a' - 10);
						else if (ch >= 'A')	ch -= ('A' - 10);
						else ch -= '0';
						char cl = *phex++;
						if ((cl < '0' || cl > '9') && (cl < 'a' || cl > 'f') && (cl < 'A' || cl > 'F')) break;
						if (cl >= 'a') cl -= ('a' - 10);
						else if (cl >= 'A')	cl -= ('A' - 10);
						else cl -= '0';
						tanz -= 2;
						*pbin++ = (char)((ch << 4) | cl);
					}
					if (tanz) cres = -tanz;	// Error in String
				}
				if (_verbose) {
					int idx = (int)(pcli - &clients[0]);
					printf("Reply(Ind.%d): '%s'[%d]\n", idx, pcli->tx_replybuf, pcli->tx_len);
					printf("Result: %d:%s\n", cres, curl_easy_strerror(cres));
					printf("Dest: %s:%d\n", inet_ntoa(pcli->client_socket.sin_addr), ntohs(pcli->client_socket.sin_port));
				}

				if (!cres) { // Send UDP Reply
					int tres = send_reply_to_udp_client(pcli, bin_reply, pcli->tx_len / 2);
					if (tres) {
						printf("ERROR: SendTo UDP Client Socket failed (%d)\n", tres);
						cp_err++;
					}
				}
				curl_multi_remove_handle(cm, e);
				curl_easy_cleanup(e);

			} // else ignore msg
		}
		if (cactive) curl_multi_wait(cm, NULL, 0, 1000, NULL); // Wait (max) 1 sec per loop
		else break;
	}
	curl_multi_cleanup(cm);
	curl_global_cleanup();
	return cp_err;
}

// Server-Loop - Return only if Error
int udp_server_loop(void) {
	int wcnt = 0;
	while (1) {
		if (_verbose) {
			printf(" Wait(%d) ", wcnt++);
			fflush(stdout);
		}
		int res = wait_for_udp_data();
		if (res < 0) {
			printf("ERROR: 'select()' failed (%d)\n", res);
			return res;
		}
		else if (!res) continue; // Timeout

		int cidx;
		for (cidx = 0; cidx < MAX_CLIENTS; cidx++) {
			CLIENT* pcli = &clients[cidx];
			res = receive_from_udp_socket(cidx);
			if (res < 0) {
				printf("ERROR: ReceiveFrom UDP Server Socket Index[%d] failed (%d)\n", cidx, res);
				return res;
			}
			else if (!res) break; // Nothing (more) received
		}
		res = run_curl(cidx); // cidx: Nr of packets received
		if (res) return res;

		/* Optional Delay for Slow CurlProcessing Test
				printf("*** Extra Wait ***\n");
		#ifdef _MSC_VER	// MS VS only
				Sleep(5000);
		#else // GCC
				sleep(5);
		#endif
		*/
	}
}

// --- MAIN ---
int main(int argc, char* argv[]) {
	printf("--- %s - %s - (C) Joembedded\n", argv[0], VERSION);
	int aerr = 0;
	for (int i = 1; i < argc; i++) {
		if (*argv[i] == '-') switch (argv[i][1]) {
		case 'v':
			_verbose = 1;
			printf("Verbose: on\n");
			break;
		case 'p':
			udp_port = atoi(&argv[i][2]);
			if (udp_port < 1024 || udp_port>65535) {
				printf("ERROR: UDP Port: 1024..65535\n");
				aerr++;
			}
			break;
		case 'c':
			if (strlen(&argv[i][2]) < (URL_MAXLEN)) {
				strcpy(callscript, &argv[i][2]);
			}else {
				printf("ERROR: Callscript Len (<%d)\n", URL_MAXLEN);
				aerr++;
			}
			break;
		default:
			aerr++;
		}
		else aerr++;
	}
	if (aerr) { 
		printf("Options:\n");
		printf(" -v: Verbose\n");
		printf(" -pXXX: UDP Port (1024..65535, Def.:%d)\n",UDP_PORT);
		printf(" -cSCRIPT: Callscript (Def.:'%s')\n",CALLSCRIPT);
		return -1;
	}

	int res = init_udp_server_socket();
	if (res) {
		printf("ERROR: Init UDP Server Socket failed (%d)\n", res);
		return -1;
	}
	else {
		printf("Script: '%s'\n", callscript);
		printf("Wait on UDP Port %d...\n", udp_port);
		udp_server_loop();
	}
	close_udp_server_socket();
	return 0;
}
//
