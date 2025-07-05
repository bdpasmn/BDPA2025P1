<!DOCTYPE html>
<html lang="en" class="bg-gray-900 text-white">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>qOverflow - Home</title>
    <script src="https://cdn.tailwindcss.com"></script>
  </head>
  <body class="min-h-screen font-sans flex flex-col">
    <div class="flex w-full h-screen p-6 gap-6 overflow-hidden">
      <aside class="w-80 bg-gray-800 rounded-2xl p-6 hidden md:flex flex-col max-h-[calc(100vh-3rem)] sticky top-6">
        <h1 class="text-3xl font-bold mb-6 leading-tight text-white">
          Welcome, <br />
          <span class="text-blue-400">sir_suds_a_lot</span>!
        </h1>
        <button class="mt-auto bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-lg font-semibold transition duration-200 shadow-md" onclick="document.getElementById('modal').classList.remove('hidden')">
          Ask Question
        </button>
      </aside>

      <main class="flex-1 flex flex-col min-h-0 overflow-y-auto pr-2">
        <div class="mb-4 border-b border-gray-700 flex-shrink-0">
          <ul class="flex gap-6 text-sm font-medium">
            <li><button class="text-blue-400 border-b-2 border-blue-600 pb-2">Recent</button></li>
            <li><button class="text-gray-400 hover:text-blue-400">Best</button></li>
            <li><button class="text-gray-400 hover:text-blue-400">Most Interesting</button></li>
            <li><button class="text-gray-400 hover:text-blue-400">Hottest</button></li>
          </ul>
        </div>
        <div class="space-y-6">
          <div class="bg-gray-800 p-5 rounded-xl hover:bg-gray-700 transition">
            <a href="#" class="text-lg font-semibold text-blue-400 hover:underline">Does Hogwarts accept muggles?</a>
            <p class="text-sm text-gray-300">Do u think Hogwarts would accept a muggle like me? I wanna build a wizard (insert crying)</p>
            <div class="text-sm text-gray-400 flex gap-6 mt-2">
              <span>179 votes</span><span>16 answers</span><span>362 views</span>
            </div>
            <div class="flex justify-between text-sm text-gray-400 mt-2">
              <span><span class="w-2 h-2 bg-white rounded-full inline-block"></span> slimeOasis - level 4</span>
              <span>Posted 3 hours ago</span>
            </div>
          </div>

          <div class="bg-gray-800 p-5 rounded-xl hover:bg-gray-700 transition">
            <a href="#" class="text-lg font-semibold text-blue-400 hover:underline">Do you wanna build a snowman?</a>
            <p class="text-sm text-gray-300">Do you wanna build a snowman? Or ride our bikes around the halls? I never see you anymore, come out the door. It’s like you’ve gone away... We used to be best buddies, and now we’re not. I wish you would tell me why... Do you wanna build a smowman? It doesnt have to be a snowman :)</p>
            <div class="text-sm text-gray-400 flex gap-6 mt-2">
              <span>163 votes</span><span>7 answers</span><span>564 views</span>
            </div>
            <div class="flex justify-between text-sm text-gray-400 mt-2">
              <span><span class="w-2 h-2 bg-white rounded-full inline-block"></span> olaf - level 2</span>
              <span>Posted 7 hours ago</span>
            </div>
          </div>

          <div class="bg-gray-800 p-5 rounded-xl hover:bg-gray-700 transition">
            <a href="#" class="text-lg font-semibold text-blue-400 hover:underline">Who lives in a pineapple under the sea?</a>
            <p class="text-sm text-gray-300">Guyssssssssssss, help me! I forgot if it was Squidward or SpongeBob :(</p>
            <div class="text-sm text-gray-400 flex gap-6 mt-2">
              <span>512 votes</span><span>42 answers</span><span>1.2k views</span>
            </div>
            <div class="flex justify-between text-sm text-gray-400 mt-2">
              <span><span class="w-2 h-2 bg-white rounded-full inline-block"></span> potato - level 1</span>
              <span>Posted 1 day ago</span>
            </div>
          </div>

          <div class="bg-gray-800 p-5 rounded-xl hover:bg-gray-700 transition">
            <a href="#" class="text-lg font-semibold text-blue-400 hover:underline">Which version of the Lorax should I get for my birthday?</a>
            <p class="text-sm text-gray-300">I love the preppy Lorax but might have a few too many. On the other hand, regular Lorax is a little plain... Which is better? I can't be a slay preppy without preppy loraxs? Right? But now I hav 1.2k preppy loraxs so I'm wondering if it's time for a change? But what if that makes me a naur slay softie??? Ewwww, I can't be a softie!! I need to be a preppy! So what do I do??????? Do i get a preppy lorax or a regular lorax???????</p>
            <div class="text-sm text-gray-400 flex gap-6 mt-2">
              <span>88 votes</span><span>11 answers</span><span>245 views</span>
            </div>
            <div class="flex justify-between text-sm text-gray-400 mt-2">
              <span><span class="w-2 h-2 bg-white rounded-full inline-block"></span> preppyLorax - level 3</span>
              <span>Posted 5 days ago</span>
            </div>
          </div>

          <div class="bg-gray-800 p-5 rounded-xl hover:bg-gray-700 transition">
            <a href="#" class="text-lg font-semibold text-blue-400 hover:underline">Why do my socks keep disappearing?</a>
            <p class="text-sm text-gray-300">Is there a secret sock dimension? I’ve lost like 37 socks this year. Teach me the ways of the sockss!</p>
            <div class="text-sm text-gray-400 flex gap-6 mt-2">
              <span>321 votes</span><span>27 answers</span><span>879 views</span>
            </div>
            <div class="flex justify-between text-sm text-gray-400 mt-2">
              <span><span class="w-2 h-2 bg-white rounded-full inline-block"></span> theGrinch - level 2</span>
              <span>Posted 10 days ago</span>
            </div>
          </div>
        </div>
      </main>
    </div>

    <div id="modal" class="fixed inset-0 bg-black/60 flex items-center justify-center hidden z-50">
      <div class="bg-gray-800 w-full max-w-3xl p-8 rounded-xl shadow-xl space-y-5">
        <div class="flex justify-between items-center">
          <h2 class="text-2xl font-semibold text-white">Ask a Question</h2>
          <button class="text-gray-400 hover:text-white" onclick="document.getElementById('modal').classList.add('hidden')">✕</button>
        </div>
        <div>
          <label class="text-gray-300 text-sm">Question Title • Max 150 characters</label>
          <input type="text" maxlength="150" placeholder="Enter your question title..." class="w-full mt-1 p-3 bg-gray-700 border border-gray-600 rounded-md text-white placeholder-gray-400" />
        </div>
        <div>
          <label class="text-gray-300 text-sm">Question Body • Max 3000 characters</label>
          <textarea rows="8" maxlength="3000" placeholder="Explain your question in detail..." class="w-full mt-1 p-3 bg-gray-700 border border-gray-600 rounded-md text-white placeholder-gray-400 resize-none"></textarea>
        </div>
        <div class="flex justify-end space-x-3 pt-4">
          <button class="px-6 py-3 rounded bg-gray-700 hover:bg-gray-600">Cancel</button>
          <button class="px-6 py-3 rounded bg-blue-600 hover:bg-blue-700 font-semibold">Post</button>
        </div>
      </div>
    </div>
  </body>
</html>
